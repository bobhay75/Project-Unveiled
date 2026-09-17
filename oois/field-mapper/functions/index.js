const { onCall, HttpsError } = require('firebase-functions/v2/https')
const { logger } = require('firebase-functions')
const admin = require('firebase-admin')
const { GoogleGenerativeAI } = require('@google/generative-ai')

admin.initializeApp()

function safeJsonParse(text) {
  try { return JSON.parse(text) } catch {
    const match = text.match(/\{[\s\S]*\}/)
    if (match) { try { return JSON.parse(match[0]) } catch { return null } }
    return null
  }
}

exports.analyzeSite = onCall({ region: 'us-central1', timeoutSeconds: 60, memory: '512MiB' }, async (request) => {
  if (!request.auth) throw new HttpsError('unauthenticated', 'Sign in is required.')
  const apiKey = process.env.GEMINI_API_KEY
  if (!apiKey) {
    logger.warn('GEMINI_API_KEY is not configured. Returning fallback.')
    return {
      summary: 'Cloud AI is not configured yet. Local evidence analysis remains available on device.',
      confidenceLabel: 'Needs More Evidence',
      signals: [],
      warnings: ['Cloud Gemini key missing. Set GEMINI_API_KEY in Firebase Functions environment.'],
      linkedSites: request.data?.nearbySites || [],
      recommendedNextDocumentation: ['Add photos with scale.', 'Record material type and size range.', 'Record water and trail relationship.']
    }
  }
  const site = request.data?.site
  const nearbySites = request.data?.nearbySites || []
  if (!site || !site.name || !site.coords) throw new HttpsError('invalid-argument', 'site with name and coords is required.')
  const prompt = `You are an evidence-focused terrain documentation assistant.\n\nAnalyze this field record for occupational mapping purposes.\n\nRules:\n- Do not claim certainty.\n- Do not encourage digging, trespass, theft, artifact disturbance, protected-site disturbance, concealment, or unsafe cave entry.\n- Use phrases like possible, probable, appears consistent with, and needs verification.\n- Focus on documentation quality, terrain relationships, lithic indicators, cave/shelter relationships, water access, spoil/debris patterns, and safe next observations.\n- Return strict JSON only.\n\nField record:\n${JSON.stringify(site, null, 2)}\n\nNearby sites:\n${JSON.stringify(nearbySites, null, 2)}\n\nReturn exactly:\n{\n  \"summary\": \"\",\n  \"confidenceLabel\": \"\",\n  \"signals\": [],\n  \"warnings\": [],\n  \"linkedSites\": [],\n  \"recommendedNextDocumentation\": []\n}`
  const genAI = new GoogleGenerativeAI(apiKey)
  const model = genAI.getGenerativeModel({ model: process.env.GEMINI_MODEL || 'gemini-1.5-flash' })
  const result = await model.generateContent(prompt)
  const text = result.response.text()
  const parsed = safeJsonParse(text)
  if (!parsed) return { summary: text.slice(0, 1200), confidenceLabel: 'Needs Review', signals: [], warnings: ['Cloud AI response could not be parsed as strict JSON.'], linkedSites: nearbySites, recommendedNextDocumentation: ['Review the AI response manually.', 'Add clearer measurable field observations.'] }
  return {
    summary: parsed.summary || '',
    confidenceLabel: parsed.confidenceLabel || 'Needs More Evidence',
    signals: Array.isArray(parsed.signals) ? parsed.signals : [],
    warnings: Array.isArray(parsed.warnings) ? parsed.warnings : [],
    linkedSites: Array.isArray(parsed.linkedSites) ? parsed.linkedSites : nearbySites,
    recommendedNextDocumentation: Array.isArray(parsed.recommendedNextDocumentation) ? parsed.recommendedNextDocumentation : []
  }
})
