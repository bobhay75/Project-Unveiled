(function attachTrustResearch(root) {
  "use strict";

  const MAX_RESULTS_PER_QUERY = 5;
  const DEFAULT_TIMEOUT_MS = 12000;
  const STOP_WORDS = new Set([
    "a", "an", "and", "are", "as", "at", "be", "because", "been", "by", "did", "do", "does",
    "for", "from", "had", "has", "have", "how", "i", "if", "in", "into", "is", "it", "its",
    "man", "of", "on", "or", "our", "that", "the", "their", "there", "this", "to", "was",
    "were", "what", "when", "where", "which", "who", "why", "with", "would"
  ]);

  function cleanText(value, maxLength = 500) {
    const decoded = String(value == null ? "" : value)
      .replace(/&nbsp;|&#160;/gi, " ")
      .replace(/&amp;/gi, "&")
      .replace(/&lt;/gi, "<")
      .replace(/&gt;/gi, ">")
      .replace(/&quot;/gi, "\"")
      .replace(/&#39;|&apos;/gi, "'")
      .replace(/&#(x[0-9a-f]+|\d+);/gi, (match, raw) => {
        const point = raw.toLowerCase().startsWith("x") ? Number.parseInt(raw.slice(1), 16) : Number.parseInt(raw, 10);
        return Number.isFinite(point) && point >= 0 && point <= 0x10ffff ? String.fromCodePoint(point) : " ";
      });
    const plain = decoded
      .replace(/<[^>]*>/g, " ")
      .replace(/[\u0000-\u0008\u000B\u000C\u000E-\u001F\u007F\u202A-\u202E\u2066-\u2069]/g, "")
      .replace(/\s+/g, " ")
      .trim();
    return plain.slice(0, maxLength);
  }

  function safeHttpUrl(value) {
    try {
      const parsed = new URL(String(value || "").trim());
      if (!/^https?:$/.test(parsed.protocol) || parsed.username || parsed.password) return "";
      const host = parsed.hostname.toLowerCase().replace(/\.$/, "");
      if (!host || host === "localhost" || host.endsWith(".localhost")) return "";
      if (/^(?:127|10|0)\./.test(host) || /^169\.254\./.test(host) || /^192\.168\./.test(host)) return "";
      if (/^172\.(?:1[6-9]|2\d|3[01])\./.test(host) || /^100\.(?:6[4-9]|[7-9]\d|1[01]\d|12[0-7])\./.test(host)) return "";
      const ipv6 = host.replace(/^\[|\]$/g, "");
      if (ipv6.includes(":") && (ipv6 === "::" || ipv6 === "::1" || /^f[cd]/.test(ipv6) || /^fe[89ab]/.test(ipv6))) return "";
      parsed.hash = "";
      return parsed.toString();
    } catch {
      return "";
    }
  }

  function coreTerms(claim) {
    const words = cleanText(claim, 300)
      .toLocaleLowerCase()
      .replace(/[^\p{L}\p{N}\s-]/gu, " ")
      .split(/\s+/)
      .filter(word => word.length >= 3 && !STOP_WORDS.has(word));
    return [...new Set(words)].slice(0, 10);
  }

  function relevanceTermKey(value) {
    let term = cleanText(value, 80).toLocaleLowerCase().replace(/[^\p{L}\p{N}]/gu, "");
    if (term.length > 5 && term.endsWith("ies")) term = `${term.slice(0, -3)}y`;
    else if (term.length > 3 && term.endsWith("s") && !/(?:ss|is|us|as|ws|ics)$/.test(term)) term = term.slice(0, -1);
    return term;
  }

  function relevanceTermKeys(value) {
    const term = relevanceTermKey(value);
    return term ? [term] : [];
  }

  function relevanceClaimTerms(claim) {
    const terms = cleanText(claim, 300)
      .toLocaleLowerCase()
      .replace(/[^\p{L}\p{N}\s-]/gu, " ")
      .split(/[\s-]+/)
      .filter(word => word.length >= 3 && !STOP_WORDS.has(word))
      .map(term => ({ term, key: relevanceTermKey(term), keys: relevanceTermKeys(term) }))
      .filter(item => item.key);
    const seen = new Set();
    return terms.filter(item => {
      if (item.keys.some(key => seen.has(key))) return false;
      item.keys.forEach(key => seen.add(key));
      return true;
    }).slice(0, 12);
  }

  function relevanceDocumentKeys(result) {
    return new Set(cleanText(`${result?.title || ""} ${result?.screeningExcerpt || result?.snippet || ""}`, 1300)
      .toLocaleLowerCase()
      .replace(/[^\p{L}\p{N}\s-]/gu, " ")
      .split(/[\s-]+/)
      .flatMap(relevanceTermKeys)
      .filter(Boolean));
  }

  function assessResultRelevance(claim, result) {
    const claimTerms = relevanceClaimTerms(claim);
    const documentKeys = relevanceDocumentKeys(result);
    const matchedTerms = claimTerms.filter(item => item.keys.some(key => documentKeys.has(key))).map(item => item.term);
    const requiredMatches = Math.min(2, claimTerms.length);
    const retained = matchedTerms.length >= requiredMatches;
    const countText = `${matchedTerms.length} of ${claimTerms.length || 0} claim terms`;
    return {
      status: retained ? "retained" : "low-overlap",
      score: matchedTerms.length,
      ratio: claimTerms.length ? Number((matchedTerms.length / claimTerms.length).toFixed(3)) : 0,
      requiredMatches,
      matchedTerms,
      claimTerms: claimTerms.map(item => item.term),
      reason: retained
        ? requiredMatches
          ? `Shown because ${countText} matched the title or provider description.`
          : "Shown because the claim supplied no usable screening terms; no metadata was hidden."
        : `Moved to the low-overlap audit because only ${countText} matched the title or provider description; ${requiredMatches} were required.`
    };
  }

  function queryFamilies(claim) {
    const neutral = cleanText(claim, 300).replace(/[?!]+$/g, "");
    const terms = coreTerms(neutral);
    const core = terms.length ? terms.join(" ") : neutral;
    return {
      neutral,
      support: `${core} evidence data report confirmation`,
      challenge: `${core} hoax fake fraud critique contradiction dissent`
    };
  }

  function buildUrl(base, params) {
    const url = new URL(base);
    Object.entries(params).forEach(([key, value]) => {
      if (Array.isArray(value)) value.forEach(entry => url.searchParams.append(key, String(entry)));
      else url.searchParams.set(key, String(value));
    });
    return url.toString();
  }

  function buildResearchPlan(claim) {
    const families = queryFamilies(claim);
    const archiveCore = coreTerms(claim).map(term => `\"${term.replace(/\"/g, "")}\"`).join(" AND ") || `\"${families.neutral}\"`;
    const archiveSupport = `(${archiveCore}) AND (evidence OR data OR report OR experiment OR archive)`;
    const archiveChallenge = `(${archiveCore}) AND (hoax OR fake OR fraud OR critique OR contradiction OR dissent)`;
    return [
      {
        id: "crossref-neutral",
        providerId: "crossref",
        provider: "Crossref",
        repository: "Scholarly DOI metadata",
        stance: "neutral",
        query: families.neutral,
        url: buildUrl("https://api.crossref.org/works", {
          "query.bibliographic": families.neutral,
          rows: MAX_RESULTS_PER_QUERY
        })
      },
      {
        id: "openalex-neutral",
        providerId: "openalex",
        provider: "OpenAlex",
        repository: "Open research catalog",
        stance: "neutral",
        query: families.neutral,
        url: buildUrl("https://api.openalex.org/works", {
          search: families.neutral,
          "per-page": MAX_RESULTS_PER_QUERY
        })
      },
      {
        id: "europepmc-support",
        providerId: "europepmc",
        provider: "Europe PMC",
        repository: "Life-science publications",
        stance: "support",
        query: families.support,
        url: buildUrl("https://www.ebi.ac.uk/europepmc/webservices/rest/search", {
          query: families.support,
          format: "json",
          pageSize: MAX_RESULTS_PER_QUERY,
          resultType: "core"
        })
      },
      {
        id: "internetarchive-support",
        providerId: "internetarchive",
        provider: "Internet Archive",
        repository: "Public archive uploads",
        stance: "support",
        query: archiveSupport,
        url: buildUrl("https://archive.org/advancedsearch.php", {
          q: archiveSupport,
          "fl[]": ["identifier", "title", "creator", "date", "description", "mediatype", "collection"],
          rows: MAX_RESULTS_PER_QUERY,
          page: 1,
          output: "json"
        })
      },
      {
        id: "internetarchive-challenge",
        providerId: "internetarchive",
        provider: "Internet Archive",
        repository: "Public archive uploads",
        stance: "challenge",
        query: archiveChallenge,
        url: buildUrl("https://archive.org/advancedsearch.php", {
          q: archiveChallenge,
          "fl[]": ["identifier", "title", "creator", "date", "description", "mediatype", "collection"],
          rows: MAX_RESULTS_PER_QUERY,
          page: 1,
          output: "json"
        })
      },
      {
        id: "federalregister-neutral",
        providerId: "federalregister",
        provider: "Federal Register",
        repository: "United States federal rulemaking index",
        stance: "neutral",
        query: families.neutral,
        url: buildUrl("https://www.federalregister.gov/api/v1/documents.json", {
          "conditions[term]": families.neutral,
          per_page: MAX_RESULTS_PER_QUERY,
          order: "relevance"
        })
      }
    ];
  }

  function firstTitle(value) {
    if (Array.isArray(value)) return cleanText(value[0], 240);
    return cleanText(value, 240);
  }

  function crossrefDate(item) {
    const parts = item?.published?.["date-parts"]?.[0] || item?.issued?.["date-parts"]?.[0] || [];
    return parts.filter(Boolean).join("-");
  }

  function crossrefResults(payload) {
    return (payload?.message?.items || []).map(item => ({
      title: firstTitle(item.title),
      url: item.DOI ? `https://doi.org/${item.DOI}` : item.URL,
      author: (item.author || []).slice(0, 5).map(author => cleanText([author.given, author.family].filter(Boolean).join(" "), 100)).filter(Boolean).join(", "),
      date: crossrefDate(item),
      publisher: cleanText(item.publisher, 160),
      kind: cleanText(item.type, 80),
      snippet: cleanText(item.abstract, 420),
      screeningExcerpt: cleanText(item.abstract, 1000),
      externalId: cleanText(item.DOI, 160)
    }));
  }

  function openAlexResults(payload) {
    return (payload?.results || []).map(item => ({
      title: cleanText(item.display_name || item.title, 240),
      url: item.doi || item.primary_location?.landing_page_url || item.id,
      author: (item.authorships || []).slice(0, 5).map(entry => cleanText(entry?.author?.display_name, 100)).filter(Boolean).join(", "),
      date: cleanText(item.publication_date || item.publication_year, 40),
      publisher: cleanText(item.primary_location?.source?.display_name, 160),
      kind: cleanText(item.type, 80),
      snippet: "",
      screeningExcerpt: cleanText(Object.keys(item.abstract_inverted_index || {}).slice(0, 250).join(" "), 1000),
      externalId: cleanText(item.doi || item.id, 180)
    }));
  }

  function europePmcResults(payload) {
    return (payload?.resultList?.result || []).map(item => {
      const articleId = item.pmcid || item.pmid || item.id;
      const articleSource = item.pmcid ? "PMC" : item.pmid ? "MED" : cleanText(item.source, 20);
      return {
        title: cleanText(item.title, 240),
        url: item.doi ? `https://doi.org/${item.doi}` : articleId ? `https://europepmc.org/article/${articleSource}/${articleId}` : "",
        author: cleanText(item.authorString, 220),
        date: cleanText(item.firstPublicationDate || item.electronicPublicationDate || item.pubYear, 50),
        publisher: cleanText(item.journalTitle, 160),
        kind: cleanText(item.pubType || item.source, 80),
        snippet: cleanText(item.abstractText, 420),
        screeningExcerpt: cleanText(item.abstractText, 1000),
        externalId: cleanText(item.doi || articleId, 180)
      };
    });
  }

  function internetArchiveResults(payload) {
    return (payload?.response?.docs || []).map(item => {
      const identifier = cleanText(item.identifier, 180);
      const creator = Array.isArray(item.creator) ? item.creator.join(", ") : item.creator;
      const description = Array.isArray(item.description) ? item.description.join(" ") : item.description;
      return {
        title: firstTitle(item.title) || identifier,
        url: identifier ? `https://archive.org/details/${encodeURIComponent(identifier)}` : "",
        author: cleanText(creator, 220),
        date: cleanText(item.date, 50),
        publisher: "Internet Archive item record",
        kind: cleanText(item.mediatype, 80),
        snippet: cleanText(description, 420),
        screeningExcerpt: cleanText(description, 1000),
        externalId: identifier
      };
    });
  }

  function federalRegisterResults(payload) {
    return (payload?.results || []).map(item => ({
      title: cleanText(item.title, 240),
      url: item.html_url || item.pdf_url || item.raw_text_url,
      author: (item.agencies || []).map(agency => cleanText(agency?.name || agency?.raw_name, 100)).filter(Boolean).join(", "),
      date: cleanText(item.publication_date, 50),
      publisher: "Office of the Federal Register index",
      kind: cleanText(item.type || item.document_type, 80),
      snippet: cleanText(item.abstract, 420),
      screeningExcerpt: cleanText(item.abstract, 1000),
      externalId: cleanText(item.document_number, 100)
    }));
  }

  const PARSERS = {
    crossref: crossrefResults,
    openalex: openAlexResults,
    europepmc: europePmcResults,
    internetarchive: internetArchiveResults,
    federalregister: federalRegisterResults
  };

  function normalizedTitle(value) {
    return cleanText(value, 240)
      .toLocaleLowerCase()
      .replace(/[^\p{L}\p{N}]+/gu, " ")
      .trim();
  }

  function normalizedDoi(value) {
    let candidate = cleanText(value, 240).trim();
    if (!candidate) return "";
    const safe = safeHttpUrl(candidate);
    if (safe) {
      const parsed = new URL(safe);
      if (!["doi.org", "dx.doi.org"].includes(parsed.hostname.toLocaleLowerCase())) return "";
      candidate = parsed.pathname.replace(/^\/+/, "");
    } else {
      candidate = candidate.replace(/^doi:\s*/i, "");
    }
    try { candidate = decodeURIComponent(candidate); } catch { return ""; }
    candidate = candidate.trim();
    return /^10\.\d{4,9}\/\S+$/i.test(candidate) ? candidate.toLocaleLowerCase() : "";
  }

  function canonicalUrlIdentity(value) {
    // safeHttpUrl already normalizes host casing/default ports, removes fragments,
    // and rejects credentialed, local, and private-network destinations. Keep the
    // remaining path/query exact: stripping parameters or slashes can conflate
    // distinct artifacts on repositories that use them as record identifiers.
    return safeHttpUrl(value);
  }

  function stableIdentifiers(result) {
    const identifiers = [];
    const doi = normalizedDoi(result.url) || normalizedDoi(result.externalId);
    if (doi) identifiers.push(`doi:${doi}`);

    const externalId = cleanText(result.externalId, 180).trim();
    if (externalId && !normalizedDoi(externalId)) {
      const providerNamespace = cleanText(result.providerId || result.provider, 80)
        .toLocaleLowerCase()
        .replace(/[^a-z0-9.-]+/g, "-")
        .replace(/^-+|-+$/g, "");
      if (providerNamespace) identifiers.push(`provider-id:${providerNamespace}:${externalId}`);
    }
    return [...new Set(identifiers)];
  }

  function cleanStringList(value, fallback = []) {
    const entries = Array.isArray(value) ? value : fallback;
    return [...new Set(entries.map(item => cleanText(item, 180)).filter(Boolean))];
  }

  function relevanceSnapshot(value) {
    const input = value && typeof value === "object" ? value : {};
    return {
      status: ["retained", "low-overlap"].includes(input.status) ? input.status : "unscored",
      score: Math.max(0, Math.min(20, Number(input.score) || 0)),
      ratio: Math.max(0, Math.min(1, Number(input.ratio) || 0)),
      requiredMatches: Math.max(0, Math.min(20, Number(input.requiredMatches) || 0)),
      matchedTerms: cleanStringList(input.matchedTerms).slice(0, 20),
      claimTerms: cleanStringList(input.claimTerms).slice(0, 20),
      reason: cleanText(input.reason, 320)
    };
  }

  function variantSnapshot(result, index) {
    const provider = cleanText(result.provider, 100);
    const stance = cleanText(result.stance, 30);
    const queryId = cleanText(result.queryId, 100);
    return {
      id: cleanText(result.id, 180) || `source-result-${index + 1}`,
      providerId: cleanText(result.providerId, 80),
      provider,
      repository: cleanText(result.repository, 160),
      stance,
      queryId,
      query: cleanText(result.query, 500),
      title: cleanText(result.title, 240),
      url: canonicalUrlIdentity(result.url),
      author: cleanText(result.author, 220),
      date: cleanText(result.date, 50),
      publisher: cleanText(result.publisher, 160),
      kind: cleanText(result.kind, 80),
      snippet: cleanText(result.snippet, 420),
      screeningExcerpt: cleanText(result.screeningExcerpt || result.snippet, 1000),
      externalId: cleanText(result.externalId, 180),
      foundBy: cleanStringList(result.foundBy, provider ? [provider] : []),
      queryIds: cleanStringList(result.queryIds, queryId ? [queryId] : []),
      queryStances: cleanStringList(result.queryStances, stance ? [stance] : []),
      relevance: relevanceSnapshot(result.relevance),
      metadataOnly: true
    };
  }

  function sharedValues(left, right) {
    const rightSet = new Set(right);
    return left.filter(value => rightSet.has(value));
  }

  function normalizeResults(planItem, rawResults, claim) {
    return rawResults
      .map((item, index) => ({
        id: `${planItem.id}-${index + 1}`,
        providerId: planItem.providerId,
        provider: planItem.provider,
        repository: planItem.repository,
        stance: planItem.stance,
        queryId: planItem.id,
        query: planItem.query,
        title: cleanText(item.title, 240),
        url: safeHttpUrl(item.url),
        author: cleanText(item.author, 220),
        date: cleanText(item.date, 50),
        publisher: cleanText(item.publisher, 160),
        kind: cleanText(item.kind, 80),
        snippet: cleanText(item.snippet, 420),
        screeningExcerpt: cleanText(item.screeningExcerpt || item.snippet, 1000),
        externalId: cleanText(item.externalId, 180),
        foundBy: [planItem.provider],
        queryIds: [planItem.id],
        queryStances: [planItem.stance],
        metadataOnly: true
      }))
      .filter(item => item.title && item.url)
      .slice(0, MAX_RESULTS_PER_QUERY)
      .map(item => {
        const relevance = assessResultRelevance(claim, item);
        return { ...item, relevance };
      });
  }

  function mergeEvidenceFamilies(results) {
    const variants = (Array.isArray(results) ? results : [])
      .map(variantSnapshot)
      .filter(result => result.title && result.url)
      .map((result, index) => ({
        ...result,
        inputIndex: index,
        canonicalUrl: canonicalUrlIdentity(result.url),
        stableIds: stableIdentifiers(result),
        titleKey: normalizedTitle(result.title)
      }));

    const parents = variants.map((_, index) => index);
    const find = index => {
      let cursor = index;
      while (parents[cursor] !== cursor) cursor = parents[cursor];
      while (parents[index] !== index) {
        const next = parents[index];
        parents[index] = cursor;
        index = next;
      }
      return cursor;
    };
    const union = (left, right) => {
      const leftRoot = find(left);
      const rightRoot = find(right);
      if (leftRoot !== rightRoot) parents[rightRoot] = leftRoot;
    };

    const identityOwners = new Map();
    variants.forEach((variant, index) => {
      const keys = [
        ...variant.stableIds.map(value => `stable:${value}`),
        ...(variant.canonicalUrl ? [`canonical-url:${variant.canonicalUrl}`] : [])
      ];
      keys.forEach(key => {
        if (identityOwners.has(key)) union(index, identityOwners.get(key));
        else identityOwners.set(key, index);
      });
    });

    const grouped = new Map();
    variants.forEach((variant, index) => {
      const rootIndex = find(index);
      if (!grouped.has(rootIndex)) grouped.set(rootIndex, []);
      grouped.get(rootIndex).push(variant);
    });

    const families = [...grouped.values()].map((members, familyIndex) => {
      const familyId = `evidence-family-${familyIndex + 1}`;
      const decisions = [];
      for (let leftIndex = 0; leftIndex < members.length; leftIndex += 1) {
        for (let rightIndex = leftIndex + 1; rightIndex < members.length; rightIndex += 1) {
          const left = members[leftIndex];
          const right = members[rightIndex];
          sharedValues(left.stableIds, right.stableIds).forEach(match => {
            decisions.push({
              action: "merged",
              leftVariantId: left.id,
              rightVariantId: right.id,
              reason: "shared-stable-identifier",
              match
            });
          });
          if (left.canonicalUrl && left.canonicalUrl === right.canonicalUrl) {
            decisions.push({
              action: "merged",
              leftVariantId: left.id,
              rightVariantId: right.id,
              reason: "shared-normalized-canonical-url",
              match: left.canonicalUrl
            });
          }
        }
      }

      const publicVariants = members.map(member => {
        const { inputIndex, canonicalUrl, stableIds, titleKey, ...snapshot } = member;
        return snapshot;
      });
      const representativeIndex = members.reduce((bestIndex, member, index) => {
        const best = members[bestIndex];
        const scoreDifference = (member.relevance?.score || 0) - (best.relevance?.score || 0);
        return scoreDifference > 0 || (scoreDifference === 0 && member.inputIndex < best.inputIndex) ? index : bestIndex;
      }, 0);
      const publicRepresentative = publicVariants[representativeIndex];
      const family = {
        ...publicRepresentative,
        familyId,
        familyRelationship: members.length > 1 ? "merged-by-identity" : "single-record",
        mergeDecisions: decisions,
        identity: {
          stableIdentifiers: [...new Set(members.flatMap(member => member.stableIds))],
          canonicalUrls: [...new Set(members.map(member => member.canonicalUrl).filter(Boolean))]
        },
        variants: publicVariants,
        foundBy: [...new Set(members.flatMap(member => member.foundBy))],
        queryIds: [...new Set(members.flatMap(member => member.queryIds))],
        queryStances: [...new Set(members.flatMap(member => member.queryStances))]
      };
      family.relevance = { ...publicRepresentative.relevance };
      delete family.screeningExcerpt;
      ["snippet", "author", "date", "publisher", "kind", "externalId"].forEach(field => {
        if (!family[field]) family[field] = members.find(member => member[field])?.[field] || "";
      });
      return {
        family,
        titleKeys: [...new Set(members.map(member => member.titleKey).filter(title => title.length >= 12))]
      };
    });

    const titleClusters = new Map();
    families.forEach(({ titleKeys }, index) => {
      titleKeys.forEach(titleKey => {
        if (!titleClusters.has(titleKey)) titleClusters.set(titleKey, []);
        titleClusters.get(titleKey).push(index);
      });
    });
    let clusterCount = 0;
    titleClusters.forEach((memberIndexes, titleKey) => {
      if (memberIndexes.length < 2) return;
      clusterCount += 1;
      const memberFamilyIds = memberIndexes.map(index => families[index].family.familyId);
      memberIndexes.forEach(index => {
        const cluster = {
          clusterId: `possible-title-duplicate-${clusterCount}`,
          basis: "normalized-title-only",
          normalizedTitle: titleKey,
          decision: "retained-separately",
          reason: "Titles normalize to the same text, but no shared stable identifier or canonical URL establishes that these are the same artifact.",
          memberFamilyIds
        };
        if (!families[index].family.possibleDuplicateClusters) families[index].family.possibleDuplicateClusters = [];
        families[index].family.possibleDuplicateClusters.push(cluster);
        if (!families[index].family.possibleDuplicateCluster) families[index].family.possibleDuplicateCluster = cluster;
      });
    });

    return families.map(({ family }) => family);
  }

  async function fetchJson(planItem, fetchImpl, parentSignal, timeoutMs) {
    const controller = typeof AbortController === "function" ? new AbortController() : null;
    const abort = () => controller?.abort();
    let timedOut = false;
    if (parentSignal?.aborted) abort();
    else parentSignal?.addEventListener?.("abort", abort, { once: true });
    const timer = root.setTimeout ? root.setTimeout(() => { timedOut = true; abort(); }, timeoutMs) : null;
    try {
      const response = await fetchImpl(planItem.url, {
        method: "GET",
        headers: { Accept: "application/json" },
        mode: "cors",
        credentials: "omit",
        referrerPolicy: "no-referrer",
        signal: controller?.signal || parentSignal
      });
      if (!response?.ok) throw new Error(`HTTP ${response?.status || "error"}`);
      return await response.json();
    } catch (error) {
      if (timedOut && !parentSignal?.aborted) {
        const timeoutError = new Error(`Provider timed out after ${timeoutMs} ms.`);
        timeoutError.name = "TimeoutError";
        throw timeoutError;
      }
      throw error;
    } finally {
      if (timer != null && root.clearTimeout) root.clearTimeout(timer);
      parentSignal?.removeEventListener?.("abort", abort);
    }
  }

  async function runFederatedSearch(claim, options = {}) {
    const fetchImpl = options.fetchImpl || root.fetch;
    if (typeof fetchImpl !== "function") throw new Error("Network search is unavailable in this browser.");
    const plan = buildResearchPlan(claim);
    const startedAt = new Date().toISOString();
    const onProgress = typeof options.onProgress === "function" ? options.onProgress : () => {};
    const timeoutMs = Number(options.timeoutMs) > 0 ? Number(options.timeoutMs) : DEFAULT_TIMEOUT_MS;

    const settled = await Promise.all(plan.map(async planItem => {
      const requestedAt = new Date().toISOString();
      try {
        const payload = await fetchJson(planItem, fetchImpl, options.signal, timeoutMs);
        const parser = PARSERS[planItem.providerId];
        const results = normalizeResults(planItem, parser ? parser(payload) : [], claim);
        const retainedResultCount = results.filter(item => item.relevance.status === "retained").length;
        const lowOverlapResultCount = results.filter(item => item.relevance.status === "low-overlap").length;
        const record = {
          ...planItem,
          requestedAt,
          completedAt: new Date().toISOString(),
          status: "complete",
          resultCount: results.length,
          retainedResultCount,
          lowOverlapResultCount,
          error: ""
        };
        onProgress(record);
        return { record, results };
      } catch (error) {
        const aborted = options.signal?.aborted || error?.name === "AbortError";
        const record = {
          ...planItem,
          requestedAt,
          completedAt: new Date().toISOString(),
          status: aborted ? "cancelled" : "failed",
          resultCount: 0,
          retainedResultCount: 0,
          lowOverlapResultCount: 0,
          error: cleanText(aborted ? "Search cancelled before completion." : error?.message || "Provider request failed.", 180)
        };
        onProgress(record);
        return { record, results: [] };
      }
    }));

    const families = mergeEvidenceFamilies(settled.flatMap(item => item.results));
    const results = families.filter(family => family.variants.some(variant => variant.relevance?.status === "retained"));
    const lowOverlapResults = families.filter(family => !family.variants.some(variant => variant.relevance?.status === "retained"));
    const requiredMatchCount = Math.min(2, relevanceClaimTerms(claim).length);

    return {
      schema: "trust-worthy-source-sweep-v2",
      startedAt,
      completedAt: new Date().toISOString(),
      claim: cleanText(claim, 800),
      queries: settled.map(item => item.record),
      results,
      lowOverlapResults,
      screening: {
        policy: "claim-term-overlap-v1",
        mode: requiredMatchCount ? "applied" : "bypassed-no-usable-terms",
        requiredMatchCount,
        titleCharacterLimit: 240,
        descriptionCharacterLimit: 1000,
        returnedFamilyCount: families.length,
        retainedFamilyCount: results.length,
        screenedOutFamilyCount: lowOverlapResults.length
      }
    };
  }

  root.TrustResearch = {
    cleanText,
    safeHttpUrl,
    coreTerms,
    queryFamilies,
    buildResearchPlan,
    assessResultRelevance,
    mergeEvidenceFamilies,
    runFederatedSearch
  };
})(typeof window !== "undefined" ? window : globalThis);
