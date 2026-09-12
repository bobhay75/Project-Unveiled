"use strict";

const STORAGE_KEY = "trust-worthy-evidence-lab:v2";
const PUBLIC_APP_URL = "https://bobsome1.com/truth/lab";
const MOON_DEMO_QUESTIONS = new Set([
  "did man land on the moon",
  "did humans land on the moon",
  "did people land on the moon",
  "did astronauts land on the moon",
  "have humans landed on the moon",
  "have people landed on the moon"
]);
const ATMOSPHERE_DEMO_CLAIM = "Are persistent high-altitude aircraft trails evidence of a secret atmospheric spraying program?";
const ATMOSPHERE_DEMO_QUESTIONS = new Set([
  ATMOSPHERE_DEMO_CLAIM.toLowerCase().replace(/[?.!]+$/, "")
]);

const profiles = [
  {
    id: "historical record",
    test: /\b(history|historical|ancient|century|council|nicaea|rome|war|founded|decided|wrote|manuscript|archive|apollo|moon|landed|astronaut)\b/i,
    support: [
      "A dated primary record that directly addresses the claimed event, actor, place, and outcome.",
      "Independent contemporary observation whose information does not trace only to the claimant.",
      "Later physical or documentary evidence with a visible provenance chain."
    ],
    counter: [
      "Contemporary records or physical evidence incompatible with a necessary part of the event.",
      "Evidence that supposedly independent accounts repeat one unverified origin.",
      "A competing explanation that predicts the observed record with fewer unsupported assumptions."
    ],
    sources: ["Contemporary records and physical artifacts", "Independent tracking, archives, or witness records", "Later observations and authenticated catalogs", "Qualified analysis after the underlying record"]
  },
  {
    id: "health / science",
    test: /\b(health|medical|medicine|disease|vitamin|drug|treatment|study|scientific|climate|prevents?|cures?|symptom|doctor)\b/i,
    support: [
      "A defined population, exposure or intervention, comparison, and measurable outcome.",
      "Primary research with methods, sample size, effect size, uncertainty, and limitations.",
      "Independent replication or a systematic review that does not reuse one evidence pool."
    ],
    counter: [
      "Well-designed studies finding no effect, a smaller effect, or a plausible confounder.",
      "Evidence that correlation has been promoted to causation.",
      "Dose, population, timing, measurement, or baseline-risk differences that change the conclusion."
    ],
    sources: ["Registered protocols and original studies", "Systematic reviews and evidence-grade guidelines", "Official regulatory or safety records", "Expert commentary only after the data"]
  },
  {
    id: "legal / policy",
    test: /\b(law|legal|illegal|court|crime|criminal|contract|policy|regulation|rule|required|rights?)\b/i,
    support: [
      "The controlling jurisdiction, date, exact rule, and facts to which it allegedly applies.",
      "Current statutory, regulatory, contractual, or court text from an official source.",
      "Authoritative interpretation connecting the rule to this particular circumstance."
    ],
    counter: [
      "A superseding rule, exception, different jurisdiction, or later controlling decision.",
      "Facts that fail an element required by the rule.",
      "Evidence that guidance, opinion, or a proposal is being described as binding law."
    ],
    sources: ["Official statute, regulation, contract, or court record", "Current agency or court guidance", "Qualified analysis tied to controlling text", "News and social posts only as leads"]
  },
  {
    id: "business / performance",
    test: /\b(business|sales|sold|customer|marketing|advertis|traffic|leads?|revenue|profit|campaign|conversion|website|tickets?)\b/i,
    support: [
      "A defined outcome, baseline period, comparison period, and reliable operational data.",
      "A traceable customer path from discovery through action, with dates and channel attribution.",
      "Evidence that the proposed cause changed before the outcome and that alternatives were tested."
    ],
    counter: [
      "Pricing, availability, capacity, seasonality, reputation, offer quality, or measurement changes that better explain the result.",
      "Data showing the same decline while the proposed cause remained stable.",
      "Missing tracking, small samples, cherry-picked dates, or activity mistaken for qualified demand."
    ],
    sources: ["Owned sales, traffic, inquiry, and campaign records", "Customer and transaction evidence", "Platform reports reconciled to owned records", "Competitor observations labeled as estimates"]
  },
  {
    id: "religion / text",
    test: /\b(god|jesus|bible|biblical|scripture|church|faith|religion|apostle|gospel|doctrine|spirit)\b/i,
    support: [
      "The exact text, translation, historical setting, and scope required by the claim.",
      "Relevant passages read in context instead of a detached line.",
      "A visible separation of textual record, historical inference, theology, and belief."
    ],
    counter: [
      "Texts or early evidence that support a materially different reading.",
      "Translation, genre, authorship, dating, or context issues that narrow the conclusion.",
      "Evidence that a later doctrine is being attributed directly to an earlier source."
    ],
    sources: ["Primary text and critical editions", "Earliest relevant historical records", "Qualified scholarship representing competing readings", "Tradition and testimony clearly labeled"]
  },
  {
    id: "current public record",
    test: /\b(today|currently|now|latest|president|ceo|price|active|running|open|closed|election|202[4-9])\b/i,
    support: [
      "A precise subject, location, cutoff date, and status.",
      "Fresh first-party or official evidence showing both publication and event dates.",
      "Independent confirmation referring to the same event instead of repeating one report."
    ],
    counter: [
      "A later update, correction, retraction, or direct record contradicting the status.",
      "Evidence that the claim combines different events, dates, people, or locations.",
      "An archived or undated page being mistaken for current evidence."
    ],
    sources: ["Current first-party statement or official record", "Fresh independent reporting tied to that record", "Archived versions for change comparison", "Authenticated social records only when necessary"]
  },
  {
    id: "general factual",
    test: /.*/,
    support: [
      "A precise subject, disputed action, time, place, and observable outcome.",
      "Direct evidence capable of establishing each necessary part of the claim.",
      "Independent corroboration that does not trace to the same original assertion."
    ],
    counter: [
      "The strongest plausible competing explanation and the evidence it predicts.",
      "A reliable record contradicting any necessary part of the claim.",
      "Evidence that the sentence is ambiguous, unfalsifiable, or several claims joined together."
    ],
    sources: ["Primary or first-party record", "Independent corroborating record", "Qualified analysis with transparent methods", "Summary and social content only as leads"]
  }
];

const absoluteTerms = ["all", "always", "never", "every", "everyone", "nobody", "nothing", "only", "proves", "proven", "causes", "caused", "guarantees", "controlled", "completely", "definitely", "impossible", "undeniable"];
const vagueTerms = ["they", "people", "experts", "the media", "the government", "the church", "religion", "successful", "recently", "a lot", "many", "often", "better", "worse", "truth", "real"];

const moonCase = {
  caseId: "TW-MOON-001",
  version: "2.0",
  reviewed: "September 8, 2026",
  claim: "Human beings landed on the Moon during NASA's Apollo missions between 1969 and 1972.",
  labels: ["historical record", "scientific evidence", "adversarial review open"],
  finding: "ADVERSARIAL RECORD OPEN",
  summary: "The evidence families inspected in this pass currently fit crewed landings better than whole-fabrication or uncrewed-substitute alternatives. The staged-media hypothesis remains open at the artifact level. NASA/United States incentives, NASA-origin custody, a real Apollo 11 tape loss, and unresolved visual tests remain visible.",
  clarify: [
    "Subject: crews assigned to Apollo 11, 12, 14, 15, 16, and 17—not a vague claim that every Apollo story or image is authentic.",
    "Action and window: landing, surface work, departure, and crew return between July 1969 and December 1972.",
    "Threshold: a combined record that distinguishes human presence from robotic hardware, relayed signals, copied claims, and staged media.",
    "Revision trigger: authenticated original-generation media showing incompatible studio construction, or a coherent uncrewed record that explains telemetry, samples, traverses, retrieved hardware, sites, and crews with fewer unsupported additions."
  ],
  support: [
    "Contemporaneous mission and medical records tied to launch, translunar flight, surface operations, ascent, rendezvous, and return.",
    "Observations outside NASA control that place mission signals or vehicles at lunar distance and behave as the recorded trajectory predicts.",
    "Human-scale traverses, surface interaction, retrieved Surveyor hardware, samples from six sites, and motion or dust behavior that discriminate against a simple relay or studio model.",
    "Later terrain and hardware observations whose geometry cross-corresponds with earlier surface records."
  ],
  counter: [
    "Treat NASA mission files, direct surface media, sample custody, and NASA-operated LRO data as related evidence—not independent votes.",
    "Test the strongest whole-hoax, uncrewed-substitute, and partly staged-media hypotheses separately; evidence that defeats one may not defeat the others.",
    "Audit flag motion, shadows, stars, fiducials, dust, crater expectations, radiation, wire/movement claims, and media generation at the exact frame and artifact level.",
    "Treat missing Apollo 11 raw SSTV reels as a real custody failure while testing what other independent or alternate-generation records survive."
  ],
  sources: ["Original mission, engineering, medical, and archive records", "Attempt to obtain independently controlled tracking and adverse or Soviet records—still incomplete here", "Original-generation film, telemetry, and photographic scans", "Returned material, continuing measurements, and later orbital data", "Named skeptical exhibits retained as leads until traced to their earliest artifact"],
  hypotheses: [
    { name: "H1 · Whole fabrication", prediction: "No Apollo crewed vehicle reached lunar distance; tracking, sites, samples, and mission data must be fabricated, mistaken, or coordinated.", weakener: "Independently controlled observations or physical records that cannot reasonably originate from Earth, a relay, or later planting." },
    { name: "H2 · Uncrewed substitute", prediction: "Real launch and lunar hardware or relay operations occurred, but crews remained nearer Earth and surface evidence was produced robotically or staged.", weakener: "A pre-registered capability and timeline comparison shows the alleged substitute cannot match specific traverse, retrieval, sampling, mass, signal, ascent, or rendezvous observations without new contradictions—or direct records positively tie those acts to the crew." },
    { name: "H3 · Real mission, staged or altered media", prediction: "Mission and landing records can remain genuine while one or more public images, sequences, or explanations reveal a studio, compositing, reconstruction, or later alteration trail.", weakener: "Original-generation artifacts whose metadata, geometry, exposure, motion, and custody consistently fit the lunar environment without positive editing indicators." },
    { name: "H4 · Crewed landings broadly as recorded", prediction: "Tracking, mission dynamics, returned crews and materials, human-scale activity, later site observations, and original media should converge while preserving ordinary errors and archive gaps.", weakener: "A necessary mission element fails authentication, or a rival predicts the full record with fewer unsupported assumptions." }
  ],
  incentives: [
    "NASA, the Nixon administration, contractors, and astronauts had enormous Cold War prestige, budget, career, legal, and reputational stakes in success; their records share a claimant-side dependency.",
    "NASA-operated LRO observations and NASA-held film, telemetry, and samples are not institutionally independent of the original claim even when outside researchers analyze them.",
    "Foreign observatories and agencies reduce shared control but are not conflict-free: they can have space-program funding, prestige, diplomatic, and professional incentives.",
    "The Soviet Union had a strong incentive to expose a United States fraud, but silence is not proof; actual tracking records and knowledgeable adverse testimony must be inspected.",
    "Hoax publishers, influencers, filmmakers, laboratories, and this project can gain money, status, audience, or identity from uncovering deceit. The same audit applies to them."
  ],
  forensics: [
    { name: "Flag motion", status: "MIXED · TRANSIENT OPEN", test: "Use the original sequence, mark every hand or pole contact, model the horizontal support and vacuum damping, then compare onset and decay. Handling explains many clips; the Apollo 15 148:57 transient remains indeterminate pending first-generation stabilized footage." },
    { name: "Missing stars", status: "LOW DISCRIMINATION", test: "Recover camera, lens, film, aperture, shutter, and scan data; calculate limiting magnitude for sunlit exposures. Short daylight exposures predict no visible stars." },
    { name: "Divergent shadows / lighting", status: "OPEN LEAD · UNREPLICATED", test: "Reconstruct terrain, calibrated camera perspective, Sun direction, object heights, and penumbra. AULIS/Bilbao identifies exact frames, but this pass found no released code, verified high-bit input, blind annotations, terrain model, or uncertainty analysis sufficient to reproduce the claimed ray anomaly." },
    { name: "Crosshair occlusion", status: "ORIGINAL NEEDED", test: "Inspect first-generation film or high-bit scan. Test whether saturated bright areas and copy generations can wash out a film-plane fiducial before calling it compositing." },
    { name: "Dust and missing blast crater", status: "PARTLY TESTED", test: "Measure particle paths, suspension, ejection angle, plume pressure, soil response, and hardware pitting. Quantitative lunar-ballistic behavior matters more than a Hollywood-sized crater expectation." },
    { name: "Van Allen belts / radiation", status: "PHYSICS OBJECTION WEAKENED", test: "Recalculate trajectory, time in belts, dosimeter provenance, shielding, and solar-particle conditions. Belt passage is hazardous but not automatically lethal; mission-specific dose records still begin with NASA." },
    { name: "Apollo 11 raw SSTV reels", status: "CONFIRMED ARCHIVE GAP", test: "NASA's search concluded the 45 telemetry tapes were probably erased and reused. Inventory surviving broadcast conversions, film, audio, and telemetry separately; never claim that all originals survived or that all telemetry vanished." }
  ],
  gaps: [
    "Frame-by-frame authentication of the strongest skeptical image and video exhibits against original or highest-generation artifacts is incomplete.",
    "Publicly accessible raw Soviet mission-by-mission tracking data are sparse; adverse-expert recollections are not a substitute for the raw record.",
    "Apollo sample and direct-surface-media custody begins within NASA; independent laboratory results still need a source-by-source custody map.",
    "A quantitatively feasible 1969–72 uncrewed-substitute model has not been produced or fully costed in this record.",
    "The Apollo 11 raw SSTV telemetry backup reels remain missing and were likely reused."
  ],
  coverage: {
    scope: "Searched NASA mission, engineering, photographic, radiation, and archive records; independent tracking; ranging; site imaging; samples; gait, dust, and photogrammetry studies; and recurring hoax exhibits. Query families included: ‘Apollo 11 missing telemetry tapes’; ‘Apollo flag movement no contact’; ‘Apollo double shadows ray tracing’; ‘Apollo crosshair original scan’; ‘Apollo dust no crater’; ‘Van Allen Apollo dose’; ‘independent Apollo tracking’; and ‘uncrewed Apollo alternative’. Both support and challenge terms were used.",
    cutoff: "September 8, 2026",
    stop: "Stopped at an adversarial public-file release once each major hypothesis had a test and the known high-value gaps were disclosed; this is not an exhaustive closeout.",
    gaps: "Not exhaustively searched: classified or sealed records, every contractor archive, every non-English skeptical source, all original-generation disputed frames, and complete raw Soviet tracking logs. This first pass did not preserve every results count, exclusion decision, mutable web snapshot, or source-file hash, so exact search reproduction remains incomplete. Deleted or never-created evidence cannot be inventoried with certainty."
  },
  verified: "This dossier links records documenting claimant-side incentives and dependency; NASA's report that Apollo 11 raw SSTV backup tapes were not recovered and were likely reused; robotic reflector and sample-return capability; outside tracking accounts; later site observations; lunar material studies; and quantitative motion or dust analyses. The mutable links and underlying artifacts are not independently preserved and hashed here. None of these items alone proves a person stood on the Moon.",
  inference: "At the whole-program level, the inspected evidence families presently fit crewed landings better than whole fabrication or an uncrewed substitute. At the media-exhibit level, this dossier has not authenticated a named strongest skeptical frame or video against an original or highest-generation artifact, so it cannot close or confidently rank the staged-media hypothesis.",
  unknown: "Whether every circulated image is authentic; the contents of missing or inaccessible records; a complete independent Soviet tracking archive; and whether any undiscovered evidence would materially change the comparison.",
  evidence: [
    {
      title: "Apollo 11 Mission Report", url: "https://www.nasa.gov/wp-content/uploads/static/apollo50th/pdf/A11_MissionReport.pdf", role: "support", className: "primary", target: "H4 crewed landings · claimant-side mission record", author: "NASA Manned Spacecraft Center", date: "November 1969", checked: "Link checked September 8, 2026", origin: "Contemporaneous first-party mission record",
      independence: "Claimant-side record. It depends on NASA systems, personnel, contractors, and internal data; it is not independent corroboration.",
      incentives: "NASA and its contractors had major Cold War, funding, schedule, career, safety-liability, and reputational interests in declaring mission success.",
      custody: "NASA created and retains the report. It summarizes underlying mission data; this linked PDF is a later public copy rather than the raw telemetry.",
      falsifier: "Material contradictions with authenticated raw tracking, engineering limits, crew medical data, or independently observed trajectory and return would reduce its weight.",
      notes: "Documents the full claimed mission sequence and reported radiation dose. It establishes NASA's contemporaneous record, not the truth of that record by itself."
    },
    {
      title: "Apollo 11 Telemetry Data Recordings: A Final Report", url: "https://ntrs.nasa.gov/citations/20110011709", role: "counter", className: "primary", target: "H3 staged or altered media · archive custody", author: "NASA", date: "December 2009", checked: "Link checked September 8, 2026", origin: "NASA investigation of the missing Apollo 11 SSTV telemetry tapes",
      independence: "First-party archive-loss investigation; not independent of NASA, but adverse to a clean-custody narrative.",
      incentives: "NASA had reputational incentives to minimize an archive failure and institutional incentives to document the search accurately once the loss was public.",
      custody: "The report reconstructs tape handling and concludes the 45 reels were most likely erased, recertified, and reused; the raw reels were not recovered.",
      falsifier: "Recovery and authentication of the listed original reels, or archival records disproving the reconstructed reuse path, would change this conclusion.",
      notes: "Confirms a genuine missing-original gap. It does not say that all Apollo film, audio, telemetry, or broadcast records disappeared."
    },
    {
      title: "Apollo Experience Report: Photographic Equipment and Operations During Manned Space-Flight Programs", url: "https://ntrs.nasa.gov/api/citations/19720025202/downloads/19720025202.pdf", role: "context", className: "primary", target: "H3 visual-media tests · camera and film constraints", author: "Helmut A. Kuehnel", date: "September 1972", checked: "Link checked September 8, 2026", origin: "Contemporaneous camera and photographic-operations engineering report",
      independence: "NASA-origin technical record; independent of later online explanations but dependent on the Apollo program.",
      incentives: "Engineering teams had incentives to document successful equipment performance and to record failures needed for later missions.",
      custody: "Public PDF derives from NASA's technical-report archive; underlying film and hardware remain separately curated artifacts.",
      falsifier: "Original camera hardware, film records, or frame metadata incompatible with the report's specifications would weaken its use in visual tests.",
      notes: "Supplies camera and film context needed to test exposure, fiducial, focus, and image-generation claims. It does not authenticate every photograph."
    },
    {
      title: "AULIS frame-specific Apollo 14 shadow ray-tracing claim", url: "https://www.aulis.com/raytracing.htm", role: "counter", className: "lead", target: "H3 staged or altered media · frame AS14-68-9486", author: "Luis Bilbao / AULIS", date: "Publication date not established", checked: "Link checked September 8, 2026", origin: "Non-mainstream advocacy analysis of Apollo frame AS14-68-9486",
      independence: "Outside NASA and the Apollo program, but dependent on NASA-origin imagery. The published page does not establish the exact source scan, blind annotations, code, terrain model, or uncertainty chain.",
      incentives: "The publisher has audience, identity, and reputational incentives tied to a Moon-hoax thesis. That makes disclosure and replication necessary; it does not permit dismissal by label.",
      custody: "The web exhibit is a derived annotated image. No preserved original file hash, complete transformation history, released analysis code, or authenticated first-generation film was located in this pass.",
      falsifier: "A blinded, open-code reconstruction using a verified high-bit scan, calibrated lens, ephemeris, three-dimensional terrain, and published residuals could reproduce or reject the claimed intersection anomaly.",
      notes: "Raises a specific geometric counterclaim worth testing. It remains an open, unreplicated lead and is not counted as proof of staging or as independently authenticated evidence."
    },
    {
      title: "Jodrell Bank recording of Apollo 11 and Luna 15", url: "https://www.manchester.ac.uk/about/news/recording-of-ussrs-lunar-gatecrash-attempt-released/", role: "support", className: "independent", target: "H1 whole fabrication · lunar-direction signals", author: "University of Manchester / Jodrell Bank", date: "1969 recording; released 2009", checked: "Link checked September 8, 2026", origin: "United Kingdom observatory recording and institutional account",
      independence: "Operationally outside NASA and simultaneously concerned with the Soviet Luna 15 craft. The modern presentation is decades later and signal origin still requires technical analysis.",
      incentives: "Jodrell Bank gains scientific prestige from its historical role and had professional relationships within international astronomy, but did not control Apollo's mission systems.",
      custody: "The observatory retained and later released its recording; this page is an institutional summary rather than a full raw receiver-data archive.",
      falsifier: "A custody break, incompatible antenna pointing or Doppler history, or evidence that the material merely replayed NASA's public feed would reduce its independence.",
      notes: "Supports tracking of Apollo-related signals during Eagle's descent while Luna 15 was also monitored. It places signals better than it proves humans were aboard."
    },
    {
      title: "Ballistic Motion of Dust Particles in the Lunar Roving Vehicle Dust Trails", url: "https://pubs.aip.org/aapt/ajp/article/80/5/452/1045850/", role: "support", className: "analysis", target: "H2 uncrewed substitute and H3 studio media · dust dynamics", author: "Hsiang-Wen Hsu and Mihály Horányi", date: "May 2012", checked: "Link checked September 8, 2026", origin: "Peer-reviewed quantitative analysis of NASA-origin video",
      independence: "The analysis is outside the mission team, but its underlying footage originates with NASA. Analytical independence is partial; artifact independence is not.",
      incentives: "Authors and journal have publication and professional incentives; a striking result can benefit careers. No motive substitutes for checking equations and data.",
      custody: "Analysis uses copied mission video; original-generation provenance and compression effects must be audited for any frame-level dispute.",
      falsifier: "A feasible Earth-studio model matching the measured trajectories, timing, gravity, suit motion, and lack of atmospheric suspension would weaken its discrimination.",
      notes: "Finds dust trajectories consistent with lunar ballistic motion. It is quantitative evidence against a simple atmospheric studio, not proof against every staging technology."
    },
    {
      title: "Artemis I radiation measurements", url: "https://www.nature.com/articles/s41586-024-07927-7", role: "counter", className: "analysis", target: "Radiation impossibility objection · physical feasibility", author: "International radiation-research team", date: "September 2024", checked: "Link checked September 8, 2026", origin: "Peer-reviewed measurements from an uncrewed lunar mission",
      independence: "Independent authors analyze data from NASA's Artemis I flight and international instruments; mission-platform dependence remains.",
      incentives: "Space-radiation researchers and agencies gain funding and prestige from mission research and also face strong safety incentives to identify dangerous exposure.",
      custody: "Instrument data passed through mission and research-team systems; the paper documents methods, while raw-data access must be checked separately.",
      falsifier: "Reanalysis showing lethal belt doses for the actual Apollo paths and times, or invalid detector calibration, would change its relevance.",
      notes: "Direct modern measurements weaken the claim that any brief belt crossing must be fatal. They do not independently authenticate Apollo's reported crew doses."
    },
    {
      title: "LRO cross-registration of Apollo 17 surface and orbital images", url: "https://agupubs.onlinelibrary.wiley.com/doi/full/10.1029/2018EA000408", role: "support", className: "analysis", target: "H1 whole fabrication · site and terrain correspondence", author: "LROC / Arizona State University researchers", date: "2019", checked: "Link checked September 8, 2026", origin: "Peer-reviewed study using NASA Lunar Reconnaissance Orbiter data",
      independence: "Later observation, but the spacecraft is NASA-funded and site coordinates derive from the Apollo record. Institutionally partial, not independent of NASA.",
      incentives: "NASA, ASU, and authors benefit from successful lunar science, archive use, and mission heritage; methods and raw PDS data remain testable.",
      custody: "Digital LRO images and Apollo panoramas pass through NASA/ASU archives and processing pipelines documented by the study.",
      falsifier: "Inability to reproduce the cross-registration from raw PDS products, or features inconsistent with the earlier surface record, would weaken it.",
      notes: "Cross-registers later orbital terrain with Apollo 17 panoramas and site features. It supports site correspondence but cannot resolve individual humans."
    },
    {
      title: "KAGUYA observation of the Apollo 15 landing area", url: "https://global.jaxa.jp/press/2008/05/20080520_kaguya_e.html", role: "support", className: "independent", target: "H1 whole fabrication · foreign terrain and halo observation", author: "Japan Aerospace Exploration Agency", date: "May 20, 2008", checked: "Link checked September 8, 2026", origin: "Japanese lunar-orbiter data and interpretation",
      independence: "Institutionally separate from NASA. Interpretation uses Apollo site claims and surface photographs, so informational independence is partial.",
      incentives: "JAXA has institutional spaceflight, funding, diplomatic, and scientific-prestige interests; those stakes require disclosure rather than automatic rejection.",
      custody: "JAXA acquired and processed KAGUYA data; the press release summarizes results and should be checked against mission products for reproduction.",
      falsifier: "Failure to reproduce the terrain match and reflectivity halo from KAGUYA data, or a non-Apollo cause fitting them better, would weaken it.",
      notes: "Reports a surface-reflectivity halo and terrain match at Apollo 15. Its resolution does not show astronauts and the halo attribution is an inference."
    },
    {
      title: "Preliminary examination of lunar samples from Apollo 11", url: "https://www.science.org/doi/10.1126/science.165.3899.1211", role: "support", className: "analysis", target: "H1 whole fabrication and H2 uncrewed substitute · lunar material", author: "Apollo Lunar Sample Preliminary Examination Team", date: "September 1969", checked: "Link checked September 8, 2026", origin: "Peer-reviewed multi-laboratory examination of NASA-delivered material",
      independence: "Analytical work involved many specialists, but all Apollo sample provenance starts with NASA custody. Analysis is broader than collection provenance.",
      incentives: "Researchers and institutions gained rare-material access, publication value, prestige, and funding; false results also carried severe professional costs.",
      custody: "NASA collected, packaged, transported, allocated, and curated the samples before laboratory analysis. Each later result inherits that acquisition chain.",
      falsifier: "Material inconsistent with lunar formation, incompatible site-to-site patterns, or authenticated evidence of terrestrial or robotic substitution would weaken crewed-landing use.",
      notes: "Supports lunar origin and diverse returned material. Lunar material alone does not prove human collection because robotic sample return is possible."
    },
    {
      title: "The Apollo Number: Space Suits, Self-Support, and the Walk-Run Transition", url: "https://journals.plos.org/plosone/article?id=10.1371/journal.pone.0006614", role: "support", className: "analysis", target: "H2 uncrewed substitute and H3 studio media · human gait", author: "Christopher E. Carr and Jeremy McGee", date: "August 2009", checked: "Link checked September 8, 2026", origin: "Peer-reviewed gait analysis of NASA-origin surface footage",
      independence: "Researchers are outside the original mission team; their underlying imagery remains NASA-origin, making the evidence family only partially independent.",
      incentives: "Publication, novelty, and academic recognition are possible gains. The declared conflict statement and reproducible measurements matter more than presumed virtue.",
      custody: "Measurements derive from public Apollo footage rather than newly authenticated camera originals; generation loss remains a limitation.",
      falsifier: "A feasible stage, wire, playback, or altered-frame model reproducing the full measured gait series would reduce its discriminating value.",
      notes: "Measures multiple gait events consistent with reduced gravity plus suit mechanics. It challenges casual wire or slow-motion claims but does not close every media-authentication question."
    },
    {
      title: "APOLLO Lunar Ranging Basics", url: "https://www.apo.nmsu.edu/mainpage/apollo/lunarranging/", role: "counter", className: "independent", target: "Reflectors-prove-crew shortcut · robotic placement alternative", author: "Apache Point Observatory / New Mexico State University", date: "Continuing experiment", checked: "Link checked September 8, 2026", origin: "Earth-based laser-ranging program",
      independence: "Measurements are performed outside NASA mission operations, although array locations and some infrastructure depend on the broader space-science record.",
      incentives: "Observatory teams gain scientific funding and prestige; they also must produce repeatable timing measurements usable by other researchers.",
      custody: "Modern digital timing data have instrument and processing chains; the page is explanatory and not itself the complete dataset.",
      falsifier: "Failure by independent stations to reproduce range returns, or evidence returns do not localize to claimed arrays, would weaken hardware-location claims.",
      notes: "Supports reflector hardware at claimed sites but acknowledges robotic Soviet reflectors. It directly defeats the shortcut that reflectors alone prove crewed landing."
    }
  ]
};

const atmosphereCase = {
  caseId: "TW-ATMOS-001",
  version: "1.0",
  reviewed: "September 8, 2026",
  claim: "Persistent high-altitude aircraft trails are evidence of a current secret, large-scale harmful atmospheric spraying program conducted through routine aviation.",
  labels: ["health / science", "current public record", "adversarial review open"],
  finding: "NOT ESTABLISHED IN THIS DOSSIER · DOCUMENTED COMPONENTS",
  summary: "The linked record documents specific deliberate-release programs and serious official-reporting gaps. This dossier does not establish the full current mass-spraying claim and is not a global audit. A trail photograph alone cannot identify substance, operator, intent, exposure, or harm.",
  clarify: [
    "Separate operator, substance, aircraft/platform, purpose, place, dates, exposure route, dose, and claimed harm; the word ‘chemtrails’ hides several independent propositions.",
    "Separate persistent contrails and routine aviation emissions from disclosed cloud seeding, solar-geoengineering research, private releases, historic covert programs, and a current covert mass program.",
    "Threshold for the mass-program claim: authenticated direct plume or payload evidence plus operator/logistics records and exposure-to-harm evidence that survive contamination and ordinary-contrail explanations.",
    "Revision trigger: controlled replicated sampling, authenticated aircraft modifications or procurement, or coherent insider/operational records linking the observed trails to an agent, sponsor, purpose, and dose."
  ],
  support: [
    "Original, time-and-place verified imagery matched to aircraft identity, flight path, satellite sequence, and upper-air temperature, humidity, and wind.",
    "Direct plume or deposition sampling with blanks, controls, duplicates, accredited methods, source apportionment, and a documented custody chain.",
    "Payload, procurement, maintenance, crew, base, contract, budget, and mission records consistent with the scale alleged.",
    "A quantitative chain from agent to environmental concentration, human exposure, dose, and the claimed effect."
  ],
  counter: [
    "Test ordinary ice-supersaturated contrails and routine combustion emissions against the same time, altitude, weather, and flight record.",
    "Do not infer a current secret program solely from patents, a past program, a disclosed seeding project, or a proposed geoengineering technique.",
    "Do not use NOAA database silence as proof of absence: federal oversight and reporting records are documented as incomplete and error-prone.",
    "Test whether a detected element is above background, survives blanks, identifies a source and delivery system, produces a plausible dose, and replicates independently."
  ],
  sources: ["Direct atmospheric and aircraft measurements", "Flight, weather, satellite, and radar records", "Program contracts, regulatory filings, budgets, and payload records", "Controlled environmental sampling and dose analysis", "Historic covert-program archives and authenticated whistleblower material"],
  hypotheses: [
    { name: "H1 · Ordinary contrails and aviation emissions", prediction: "Trails track flights and ice-supersaturated layers; persistence, spreading, gaps, and wind drift follow atmospheric conditions while samples show ice and known combustion products.", weakener: "Verified incompatible upper-air conditions or controlled direct plume evidence of an anomalous agent and delivery system." },
    { name: "H2 · Disclosed weather modification", prediction: "Operations use suitable clouds, named agents, specialized aircraft or generators, contracts, reports, radar records, and local target areas.", weakener: "Clear-sky cruise-altitude trails far from documented operations, or evidence incompatible with the disclosed method." },
    { name: "H3 · Solar-geoengineering research or limited release", prediction: "Modeling, laboratory work, monitoring, small tests, or limited private releases exist; climate-scale deployment would require detectable procurement, dedicated operations, and stratospheric signatures.", weakener: "Evidence that no material left controlled research—or, in the other direction, authenticated large-scale operational records." },
    { name: "H4 · Current secret mass harmful spraying", prediction: "A large program should create repeatable plume chemistry, payload hardware, supply chains, trained crews, bases, budgets, flight anomalies, environmental gradients, and exposure signatures across jurisdictions.", weakener: "Repeated flight-weather matches and controlled sampling that fit ordinary aviation without the predicted logistics or agent-to-harm chain." },
    { name: "H5 · Mixed record", prediction: "Most visible trails are ordinary while particular bounded projects, illegal releases, or misidentified cases exist and must be tested individually.", weakener: "Evidence that one mechanism explains the full set without exceptions, or that a claimed exception fails authentication." }
  ],
  incentives: [
    "Governments can have secrecy, strategic, liability, and public-order incentives; agencies also face legal reporting duties and exposure if records are false or incomplete.",
    "Airlines and manufacturers have regulatory, cost, climate-liability, and reputation incentives to favor ordinary explanations; their raw operational data still remain testable.",
    "Cloud-seeding contractors and utilities gain project revenue. Geoengineering researchers and startups can gain grants, investment, credits, publications, and status.",
    "Activists, influencers, filmmakers, paid laboratories, and alternative-media businesses can gain audience, donations, product sales, or identity from dramatic findings.",
    "Trust-Worthy can profit from sensational investigations. Its own search receipts, negative findings, revisions, and unresolved gaps must therefore remain public."
  ],
  forensics: [
    { name: "Trail photograph or video", status: "UNRESOLVED WITHOUT PROVENANCE", test: "Preserve the original file and hash; verify capture time/place; reverse-search earliest publication; identify flight and aircraft; obtain upper-air conditions and satellite sequence. Pixels alone do not reveal composition or intent." },
    { name: "Persistent, spreading, or grid-like trail", status: "NON-UNIQUE OBSERVATION", test: "Compare flight corridors and holding patterns with ice-supersaturated layers, wind shear, Sun angle, and time-lapse spread before assigning a spraying mechanism." },
    { name: "Environmental element result", status: "PRESENCE IS NOT SOURCE", test: "Use acid-clean inert containers, field and trip blanks, duplicates, upwind/downwind and pre/during/post samples, accredited speciation, background comparison, source apportionment, dispersion, exposure, and dose." },
    { name: "Aircraft tank, nozzle, patent, or manual", status: "CONTEXT UNTIL LINKED", test: "Authenticate the exact aircraft/component and show operational connection to the claimed flight, payload, agent, sponsor, place, and date. Capability does not prove use in this case." },
    { name: "Whistleblower or leaked document", status: "AUTHENTICATION REQUIRED", test: "Verify identity and access, original file metadata, custody, contemporaneous corroboration, specific falsifiable details, incentive or retaliation risks, and independence from copied online claims." }
  ],
  gaps: [
    "NOAA's weather-modification database is incomplete and contains reporting errors, so it cannot prove no undisclosed activity exists.",
    "No global direct plume-sampling program rules out every small or illegal release; absence must remain bounded to the searched record.",
    "The strongest individual photo, sample, aircraft, and whistleblower exhibits still require case-specific original artifacts and custody review.",
    "Classified, unreported, cross-border, and private operations may be inaccessible; possibility is not positive evidence that they occurred.",
    "Long-term health claims require substance-specific exposure and dose evidence, not the mere presence of aviation pollutants or trace elements."
  ],
  coverage: {
    scope: "Searched current EPA/FAA contrail records; in-situ aircraft and satellite studies; GAO cloud-seeding, geoengineering, and NOAA-reporting audits; weather-modification rules; historic covert programs; and common secret-spraying evidence categories. Query families included: ‘persistent contrail in situ ice soot’; ‘cloud seeding active programs’; ‘NOAA weather modification missing reports’; ‘solar geoengineering outdoor release’; ‘Operation Popeye’; ‘zinc cadmium sulfide dispersion’; ‘chemtrail direct plume sample’; and ‘commercial aircraft spray hardware’. Ordinary, disclosed, covert, and mixed explanations were represented.",
    cutoff: "September 8, 2026",
    stop: "Stopped at a public adversarial baseline after each distinct hypothesis had direct tests, incentives, counterevidence, and named gaps; individual exhibits remain separate investigations.",
    gaps: "Not exhaustive: every state and foreign report, classified operations, every private startup or release, all local photos and samples, deleted records, and every non-English archive. This first pass did not preserve every results count, exclusion decision, mutable web snapshot, or source-file hash, so exact search reproduction remains incomplete. Official databases are treated as evidence sources, not complete inventories."
  },
  verified: "This dossier links records reporting persistent contrails, ordinary aviation emissions, disclosed cloud seeding, geoengineering proposals and research, reported limited private releases, historic covert weather and dispersion programs, and incomplete federal weather-modification reporting. The individual activities and mutable source pages are not all independently preserved and authenticated here. These documented components do not establish the bundled mass-spraying claim.",
  inference: "For the measured and modeled cases in this reviewed record, ordinary contrail and aviation-emission mechanisms have direct support. The record does not establish a current routine-airliner program secretly applying harmful agents at large scale, and it is not broad enough to classify every observed trail. Undisclosed exceptions remain a case-specific question.",
  unknown: "Whether a particular photo, flight, local sample, private release, or classified operation represents an exception; what unreported records exist; and whether direct controlled evidence could change any case-specific conclusion.",
  evidence: [
    {
      title: "Contrails Factsheet", url: "https://www.epa.gov/system/files/documents/2025-07/epa-faa-contrails-factsheet-2025-0718.pdf", role: "counter", className: "primary", target: "H4 current secret mass spraying · ordinary-contrail alternative", author: "United States EPA, FAA, and NOAA", date: "July 2025", checked: "Link checked September 8, 2026", origin: "Joint federal agency technical summary",
      independence: "Government first-party summary drawing on published atmospheric science; not independent of United States aviation regulators.", incentives: "EPA, FAA, airlines, and government have liability, regulatory, climate-policy, and public-trust stakes. Those interests require raw-data and literature checks.", custody: "Agency-hosted PDF; it summarizes research rather than preserving the raw measurements for each trail.", falsifier: "Verified trails under incompatible atmospheric conditions or controlled detection of a deliberately added agent and delivery system would weaken an ordinary-contrail explanation.", notes: "Explains contrail formation, persistence, climate effects, and ordinary aircraft emissions. It is a starting source, not a universal audit of every flight."
    },
    {
      title: "In-situ contrail ice and soot measurements", url: "https://agupubs.onlinelibrary.wiley.com/doi/full/10.1029/2018GL079390", role: "counter", className: "analysis", target: "H4 current secret mass spraying · sampled-trail mechanism", author: "Kleine and coauthors", date: "2018", checked: "Link checked September 8, 2026", origin: "Peer-reviewed direct aircraft sampling",
      independence: "Research-team measurements are analytically separate from regulator summaries; funding, instrument calibration, and flight access still require inspection.", incentives: "Authors and funders benefit from publication and aviation-climate research; anomalous or ordinary findings can both attract attention.", custody: "Samples and instrument readings were collected in flight and processed through the study's calibration and analysis chain.", falsifier: "Calibration failure, unreproducible results, or controlled samples finding a different agent/source in matched trails would weaken generalization.", notes: "Directly measures ice residuals and soot in contrails. It supports an ordinary mechanism for sampled cases, not every trail worldwide."
    },
    {
      title: "GAO audit of NOAA weather-modification reporting", url: "https://files.gao.gov/reports/GAO-26-108013/index.html", role: "support", className: "independent", target: "Bounded component · official reporting gaps", author: "United States Government Accountability Office", date: "2026", checked: "Link checked September 8, 2026", origin: "Independent congressional watchdog audit of NOAA oversight",
      independence: "GAO is institutionally separate from NOAA but remains part of the federal government and relies on agency and state records.", incentives: "GAO gains oversight relevance by identifying failures; NOAA and reporting operators have incentives to minimize errors, burden, or noncompliance.", custody: "GAO documents its selected samples and agency record review; underlying submissions remain distributed across NOAA and state sources.", falsifier: "A complete reconciled inventory disproving the sampled omissions and error rates would change the oversight conclusion.", notes: "Finds serious omissions and errors in the federal reporting system. It blocks the shortcut that database silence proves absence."
    },
    {
      title: "GAO cloud-seeding technology assessment", url: "https://www.gao.gov/products/gao-25-107328", role: "context", className: "independent", target: "H2 disclosed weather modification", author: "United States Government Accountability Office", date: "December 2024", checked: "Link checked September 8, 2026", origin: "Technology assessment of disclosed weather modification",
      independence: "GAO is separate from commercial seeders and operating states, although it relies on their records and selected research.", incentives: "Contractors and water users may favor effectiveness claims; critics and auditors may gain from exposing uncertainty or weak oversight.", custody: "Public assessment synthesizes program and research records; it is not a payload or atmospheric sample.", falsifier: "High-quality replicated trials and complete program reporting could change its efficacy and oversight assessment.", notes: "Confirms active cloud-seeding use while finding uncertain benefits and data limitations. It does not explain clear-sky cruise-altitude contrails."
    },
    {
      title: "Operation Popeye record", url: "https://history.state.gov/historicaldocuments/frus1964-68v28/d274", role: "support", className: "primary", target: "Bounded component · historical covert weather modification", author: "United States Department of State historical archive", date: "1967 record; later declassified", checked: "Link checked September 8, 2026", origin: "Declassified diplomatic record of secret operational rainmaking",
      independence: "Government-origin record of government conduct; adverse disclosure after declassification, not outside corroboration by itself.", incentives: "Officials had wartime secrecy and operational incentives; later archival programs have legal, historical, and reputational incentives toward disclosure.", custody: "Official archival transcription of a formerly classified record; original classification and archival chain should be inspected for exhibit-level work.", falsifier: "Authentication failure or records showing the proposal never became an operation would narrow its precedent value.", notes: "Documents a real secret weather-modification precedent. It proves capability and secrecy in a bounded historical program, not present continuity."
    },
    {
      title: "Army zinc cadmium sulfide dispersion tests review", url: "https://www.ncbi.nlm.nih.gov/books/NBK233549/", role: "support", className: "analysis", target: "Bounded component · historical covert dispersion testing", author: "National Research Council", date: "1997", checked: "Link checked September 8, 2026", origin: "National Academies review of historical United States Army dispersion testing",
      independence: "External scientific review of government records, commissioned in a government context; more independent than the operating Army but not conflict-free.", incentives: "Government faced liability and trust risks; reviewers faced scientific and public-accountability incentives.", custody: "Review synthesizes Army records and exposure evidence; it is not the original release log or environmental sample.", falsifier: "Original records disproving the releases or revealing materially different agents, scale, or exposure would alter its conclusions.", notes: "Documents undisclosed population-area dispersion tests. It is important secrecy precedent and still does not establish a current aviation program."
    },
    {
      title: "EPA geoengineering frequently asked questions", url: "https://www.epa.gov/geoengineering/frequent-questions", role: "context", className: "primary", target: "H3 geoengineering research and reported limited releases", author: "United States EPA", date: "updated 2026", checked: "Link checked September 8, 2026", origin: "Current agency disclosure page on geoengineering research and releases",
      independence: "First-party federal account; claims about federal activity require outside audit and claims about private activity depend on reported information.", incentives: "Agencies have public-trust, policy, research-funding, and liability interests; private firms have investment and commercial interests.", custody: "Live web page can change; an investigation should preserve dated snapshots and follow its linked underlying records.", falsifier: "Authenticated operational records contradicting its deployment claims, or missing disclosed releases, would reduce its completeness.", notes: "Separates research, limited private activity, and proposed deployment. It does not prove that unreported activity is absent."
    },
    {
      title: "US5003186A · Stratospheric Welsbach seeding patent", url: "https://patents.google.com/patent/US5003186A/en", role: "context", className: "primary", target: "H3 capability proposal · not deployment evidence", author: "David B. Chang and I-Fu Shih / Hughes Aircraft Company", date: "filed April 23, 1990; granted March 26, 1991", checked: "Link checked September 8, 2026", origin: "Public patent record describing a proposed atmospheric-seeding method",
      independence: "The patent is outside later government denial or activist arguments, but it establishes only that inventors claimed a method; it is not an operational log, procurement record, or flight record.",
      incentives: "Inventors and Hughes had commercial, intellectual-property, defense-contract, and reputational incentives to claim novelty and usefulness. A patent examiner does not certify deployment or efficacy.",
      custody: "Google Patents presents a public copy and links patent-family records; exhibit-grade use should preserve the official USPTO document and prosecution history with hashes.",
      falsifier: "The patent's capability relevance would weaken if the described method is physically infeasible; its deployment relevance remains zero unless independently linked to hardware, procurement, operators, dates, and actual releases.",
      notes: "Confirms that a stratospheric particle-seeding concept was patented. It is legitimate capability context and does not prove that the method was built, effective, or secretly used."
    },
    {
      title: "Geoengineering Watch 2019 aerial-sampling claim", url: "https://old1.geoengineeringwatch.org/lab-tests-2/", role: "support", className: "lead", target: "H4 current secret mass spraying · claimed direct plume sampling", author: "GeoengineeringWatch.org / Dane Wigington", date: "sampling claimed in 2019; page checked 2026", checked: "Link checked September 8, 2026", origin: "Non-mainstream advocacy page claiming aircraft-based trail sampling and laboratory analysis",
      independence: "Outside regulators, airlines, and mainstream atmospheric institutions, but the claimant organization selected the flights, narrative, and published evidence. Laboratory and aircraft independence must be established from underlying records.",
      incentives: "The organization can gain audience, donations, documentary revenue, identity, and reputational benefit from confirming covert spraying; it also risks credibility if a transparent replication fails.",
      custody: "This public page does not by itself supply a complete sample manifest, raw instrument files, field and trip blanks, calibration history, flight-weather match, chain-of-custody signatures, laboratory package, or preserved artifact hashes.",
      falsifier: "Independent review of raw flight, sampling, blank, calibration, speciation, background, source-apportionment, and lab records could confirm an anomalous agent or show ordinary contamination and aviation sources explain the measurements.",
      notes: "This is a named non-mainstream countercase, not a dismissed slogan. It remains an unverified lead until the original records establish what was sampled, from which plume, with what controls, and what the result proves."
    },
    {
      title: "Expert assessment of a secret large-scale spraying claim", url: "https://doi.org/10.1088/1748-9326/11/8/084011", role: "counter", className: "analysis", target: "H4 current secret mass spraying · selected evidence categories", author: "Shearer and coauthors", date: "2016", checked: "Link checked September 8, 2026", origin: "Peer-reviewed expert survey and evaluation of commonly offered evidence",
      independence: "Researchers are separate from aviation operations but use expert judgment and selected exhibits rather than a global direct field audit.", incentives: "Authors, experts, journal, and funders have professional, policy, and reputational interests; consensus is not a substitute for exhibit testing.", custody: "The paper evaluates presented photographs and sample claims; its result depends on the selection and quality of those inputs.", falsifier: "Controlled direct plume evidence, authenticated operational records, or a valid exhibit the surveyed explanations cannot fit would weaken its conclusion.", notes: "Provides counterevidence to a widespread current program. It cannot prove impossibility, and its expert-survey design must remain visible."
    }
  ]
};

const reviewedCases = [moonCase, atmosphereCase];

const byId = id => document.getElementById(id);
const nodes = {
  form: byId("claim-form"), input: byId("claim-input"), count: byId("char-count"), error: byId("claim-error"), results: byId("results"), toast: byId("toast"),
  evidenceForm: byId("evidence-form"), coverageForm: byId("coverage-form"), evidenceList: byId("evidence-list"), savedCases: byId("saved-cases"),
  researchResults: byId("research-results"), researchQueries: byId("research-query-list")
};

const emptyCoverage = () => ({ scope: "", cutoff: "", stop: "", gaps: "" });
const emptyResearchRun = () => ({ schema: "trust-worthy-source-sweep-v1", status: "idle", startedAt: "", completedAt: "", claim: "", queries: [], results: [] });
const emptyArchive = () => ({ receipt: "", hash: "", verified: "" });
let state = { claim: "", map: null, evidence: [], coverage: emptyCoverage(), coverageOrigin: "none", research: emptyResearchRun(), curated: false, caseId: null, recordVersion: null, reviewedAt: null, parent: null, archive: emptyArchive() };
let lastReport = "";
let receiptRevision = 0;
let researchRevision = 0;
let researchController = null;
let lastResearchStartedAt = 0;
let toastTimer = null;
let docketMutationPending = false;
let docketRevision = 0;
let archiveRevision = 0;
let docketReadIssue = "";

function normalizedClaim(value) {
  return String(value || "")
    .replace(/[\u0000-\u001F\u007F\u202A-\u202E\u2066-\u2069]/g, " ")
    .trim()
    .replace(/\s+/g, " ");
}

function claimEditorDiffers() {
  return Boolean(state.map && normalizedClaim(nodes.input.value) !== state.claim);
}

function updateClaimEditorGate() {
  const dirty = claimEditorDiffers();
  const running = state.research?.status === "running";
  const noRecord = !state.map;
  const warning = byId("claim-sync-warning");
  warning.hidden = !dirty;
  nodes.results.classList.toggle("is-stale", dirty);
  byId("run-research").disabled = noRecord || running || dirty;
  byId("save-case").disabled = noRecord || running || dirty || docketMutationPending;
  ["copy-report", "share-report", "print-report", "copy-receipt", "copy-receipt-json"].forEach(id => {
    byId(id).disabled = noRecord || running || dirty;
  });
  byId("copy-search-receipt").disabled = running || dirty || !state.research?.queries?.length;
  ["triage-link", "deep-link", "hyper-link"].forEach(id => {
    const link = byId(id);
    link.classList.toggle("is-disabled", dirty);
    link.setAttribute("aria-disabled", dirty ? "true" : "false");
  });
  [nodes.coverageForm, nodes.evidenceForm].forEach(form => {
    form.querySelectorAll?.("input, textarea, select, button").forEach(control => { control.disabled = running || dirty; });
  });
  nodes.results.querySelectorAll?.("[data-attach-research], [data-remove-source]").forEach(control => { control.disabled = dirty; });
  return dirty;
}

function uniqueMatches(text, terms) {
  return terms.filter(term => new RegExp(`\\b${term.replace(/\s+/g, "\\s+")}\\b`, "i").test(text));
}

function classifyClaim(claim) {
  const matches = profiles.filter(profile => profile.id !== "general factual" && profile.test.test(claim));
  return matches.length ? matches : [profiles[profiles.length - 1]];
}

function isMoonClaim(claim) {
  const key = normalizedClaim(claim).toLowerCase().replace(/[?.!]+$/, "");
  if (MOON_DEMO_QUESTIONS.has(key)) return true;
  const topic = /\b(moon|apollo(?:\s+(?:11|12|14|15|16|17|missions?|program))?)\b/i.test(key);
  const dispute = /\b(hoax|fake|faked|fraud|staged|studio|conspiracy|real|landed|landing|walked|astronauts?|humans?|people|man)\b/i.test(key);
  return topic && dispute;
}

function isAtmosphereClaim(claim) {
  const key = normalizedClaim(claim).toLowerCase().replace(/[?.!]+$/, "");
  if (ATMOSPHERE_DEMO_QUESTIONS.has(key)) return true;
  if (/\bchem\s*-?trails?\b/i.test(key)) return true;
  const program = /\b(secret|covert|hidden|undisclosed|harmful|mass)\b/i.test(key);
  const release = /\b(spray(?:ing|ed)?|aerosols?|aircraft\s+trails?|atmospheric\s+release)\b/i.test(key);
  return program && release;
}

function stageTargetId(stage) {
  return ({ search: "research-sweep", test: "evidence-title", judge: "boundary-title" })[stage] || null;
}

function setActiveStage(stage) {
  document.querySelectorAll(".stage").forEach(button => {
    const active = button.dataset.stage === stage;
    button.classList.toggle("is-active", active);
    if (active) button.setAttribute("aria-current", "step");
    else button.removeAttribute("aria-current");
  });
}

function reviewedCaseById(caseId) {
  return reviewedCases.find(item => item.caseId === caseId) || null;
}

function currentReviewedCase() {
  return state.curated ? reviewedCaseById(state.caseId) : null;
}

function trustedParentRecord(parent = state.parent) {
  if (!parent?.caseId) return null;
  const record = reviewedCaseById(parent.caseId);
  return record && String(parent.version) === String(record.version) ? record : null;
}

function recordedAtLabel(value) {
  return String(value || "").replace(/^Link checked/i, "URL recorded");
}

function buildClaimMap(claim) {
  const matched = classifyClaim(claim);
  const flags = uniqueMatches(claim, absoluteTerms);
  const vague = uniqueMatches(claim, vagueTerms);
  const support = [...new Set(matched.flatMap(profile => profile.support))].slice(0, 6);
  const counter = [...new Set(matched.flatMap(profile => profile.counter))].slice(0, 6);
  const sources = [...new Set(matched.flatMap(profile => profile.sources))].slice(0, 6);
  const clarify = [];
  if (/\?$/.test(claim)) clarify.push("Restate the question as a proposition that evidence could support or contradict; keep the original question beside it.");
  if (/\b(they|people|experts|the media|the government|the church)\b/i.test(claim)) clarify.push("Name the exact person, organization, institution, or group instead of a floating collective.");
  if (/\b(today|currently|now|latest|recently)\b/i.test(claim)) clarify.push("Set an exact cutoff date and location; current claims decay quickly.");
  else if (!/\b(\d{4}|century|year|month|day|before|after|during)\b/i.test(claim)) clarify.push("Set the event or measurement window so the claim cannot move during research.");
  if (/\b(cause[sd]?|because|led to|resulted in|prevents?|cures?)\b/i.test(claim)) clarify.push("Define the proposed cause, outcome, timing, and alternative causes that must be ruled out.");
  if (/\b(and|but|while|as well as)\b/i.test(claim)) clarify.push("Separate genuinely independent propositions; one supported part cannot carry the others.");
  if (!clarify.length || vague.length || flags.length) clarify.push("Define the subject, disputed action, place, outcome, and evidence threshold before searching.");
  clarify.push("Write down what evidence would make you revise or abandon the preferred conclusion before collecting support.");
  return {
    claim,
    labels: matched.map(profile => profile.id),
    flags,
    vague,
    clarify: [...new Set(clarify)].slice(0, 5),
    support,
    counter,
    sources,
    hypotheses: [
      { name: "H1 · Claim substantially correct", prediction: "Predict the specific observable records, measurements, artifacts, timing, and consequences that should exist if the claim is correct.", weakener: "Name a necessary observation whose failure would materially weaken or abandon this explanation." },
      { name: "H2 · Strongest serious rival", prediction: "Predict what the same record should look like under the strongest competing explanation—not a weak caricature.", weakener: "Name evidence that would distinguish this rival from H1 rather than merely fit both." },
      { name: "H3 · Mixed, bounded, or miscaptioned record", prediction: "Test whether some events or artifacts are genuine while the wording combines separate, altered, exaggerated, or unresolved parts.", weakener: "Show why one unified explanation fits better than a mixed record."
      }
    ],
    incentives: [
      "Record financial, career, institutional, political, ideological, legal, and reputational gains or costs for the claimant, authorities, critics, publishers, funders, and investigators.",
      "Cite the factual basis for an alleged conflict. Motive can change reliability weight; it does not by itself prove deception.",
      "Trace information, institutional, and funding dependence to the root record. Different domains or repeated headlines do not create independence.",
      "Audit Trust-Worthy's own incentive to produce a dramatic answer or satisfy the customer."
    ],
    forensics: [
      { name: "Image / video / audio", status: "UNTESTED", test: "Locate the original or highest-generation artifact, preserve a hash and metadata, trace earliest publication, record transformations, and compare competing physical or editing predictions." },
      { name: "Document / dataset", status: "UNTESTED", test: "Authenticate creator, version, custody, missing pages or rows, collection method, exclusions, later edits, and the root data behind summaries." },
      { name: "Absence claim", status: "UNTESTED", test: "Before treating missing evidence as meaningful, show why it should have been created, preserved, found in the searched locations, and visible to the investigator." }
    ],
    gaps: [
      "No search has been run in this browser record.",
      "No source interests, custody, dependency, or artifact authenticity have been independently checked.",
      "No supportive, challenging, and neutral search-query families have been recorded."
    ],
    coverage: emptyCoverage(),
    verified: "Nothing yet. No independently inspected sources are attached to this case.",
    inference: "The wording identifies a proposition worth testing, but wording and repetition supply no evidence for its conclusion.",
    unknown: "Whether direct evidence establishes every necessary part of the claim and survives the strongest credible competing explanation."
  };
}

function safeHttpUrl(raw) {
  if (!raw) return "";
  try {
    const parsed = new URL(raw);
    if (!["http:", "https:"].includes(parsed.protocol) || parsed.username || parsed.password) return "";
    const host = parsed.hostname.toLowerCase().replace(/^\[|\]$/g, "");
    if (!host || host === "localhost" || host.endsWith(".localhost") || host.endsWith(".local") || host.endsWith(".localdomain") || host === "::1" || host === "::" || /^(?:fc|fd|fe[89ab]|ff)/i.test(host) || host.startsWith("::ffff:")) return "";
    const octets = host.split(".").map(Number);
    if (octets.length === 4 && octets.every(part => Number.isInteger(part) && part >= 0 && part <= 255)) {
      if (octets[0] === 0 || octets[0] === 10 || octets[0] === 127 || octets[0] >= 224 ||
          (octets[0] === 100 && octets[1] >= 64 && octets[1] <= 127) ||
          (octets[0] === 169 && octets[1] === 254) || (octets[0] === 192 && octets[1] === 168) ||
          (octets[0] === 172 && octets[1] >= 16 && octets[1] <= 31) ||
          (octets[0] === 192 && octets[1] === 0) || (octets[0] === 198 && (octets[1] === 18 || octets[1] === 19 || (octets[1] === 51 && octets[2] === 100))) ||
          (octets[0] === 203 && octets[1] === 0 && octets[2] === 113)) return "";
    }
    return parsed.href;
  } catch { return ""; }
}

function canonicalUrl(raw) {
  const safe = safeHttpUrl(raw);
  if (!safe) return "";
  const parsed = new URL(safe);
  if ((parsed.protocol === "https:" && parsed.port === "443") || (parsed.protocol === "http:" && parsed.port === "80")) parsed.port = "";
  return parsed.href;
}

function dedupeUrl(raw) {
  const canonical = canonicalUrl(raw);
  if (!canonical) return "";
  const parsed = new URL(canonical);
  parsed.hash = "";
  for (const key of [...parsed.searchParams.keys()]) {
    if (/^(utm_.+|fbclid|gclid|mc_cid|mc_eid)$/i.test(key)) parsed.searchParams.delete(key);
  }
  parsed.searchParams.sort();
  return parsed.href.replace(/\/$/, "");
}

function sourceMetrics(evidence, coverage = emptyCoverage()) {
  const usable = evidence.filter(item => safeHttpUrl(item.url) && (item.checked || item.recorded) && item.origin && item.notes && item.notes.length >= 30);
  return {
    primary: usable.some(item => item.className === "primary"),
    independent: usable.some(item => item.className === "independent" && String(item.independence || "").length >= 30),
    counter: usable.some(item => item.role === "counter" && item.className !== "lead" && String(item.falsifier || "").length >= 30),
    provenance: evidence.length > 0 && evidence.every(item => safeHttpUrl(item.url) && (item.checked || item.recorded) && item.origin && item.notes && item.notes.length >= 30),
    incentives: usable.length > 0 && usable.length === evidence.length && evidence.every(item => String(item.incentives || "").length >= 30),
    custody: usable.length > 0 && usable.length === evidence.length && evidence.every(item => String(item.custody || "").length >= 30),
    falsifier: usable.length > 0 && usable.length === evidence.length && evidence.every(item => String(item.falsifier || "").length >= 30),
    coverage: String(coverage.scope || "").length >= 60 && String(coverage.cutoff || "").length >= 4 && String(coverage.stop || "").length >= 20 && String(coverage.gaps || "").length >= 30
  };
}

function dependencyHosts(evidence) {
  const counts = new Map();
  evidence.forEach(item => {
    const safe = safeHttpUrl(item.url);
    if (!safe) return;
    const host = new URL(safe).hostname.replace(/^www\./, "");
    counts.set(host, (counts.get(host) || 0) + 1);
  });
  return counts;
}

function sourceFlags(item, evidence) {
  const flags = [];
  const safe = safeHttpUrl(item.url);
  if (!safe) flags.push("No public HTTP(S) link");
  if (!item.checked && !item.recorded) flags.push("Capture time not recorded");
  if (item.className === "lead") flags.push("Unverified lead");
  if (!item.target) flags.push("Target claim not recorded");
  if (!item.origin) flags.push("Provenance gap");
  if (!item.independence || item.independence.length < 30) flags.push("Dependency not explained");
  if (!item.incentives || item.incentives.length < 30) flags.push("Stake / incentives not recorded");
  if (!item.custody || item.custody.length < 30) flags.push("Custody / transformation gap");
  if (!item.falsifier || item.falsifier.length < 30) flags.push("No weakening condition");
  if (!item.notes || item.notes.length < 30) flags.push("Thin evidence note");
  if (safe) {
    const parsed = new URL(safe);
    const host = parsed.hostname.replace(/^www\./, "");
    if (parsed.search) flags.push("Tracking/query parameters present");
    if ((dependencyHosts(evidence).get(host) || 0) > 1) flags.push("Same-domain dependence: inspect lineage");
  }
  return flags;
}

function addList(id, items) {
  const target = byId(id);
  target.replaceChildren(...items.map(item => {
    const li = document.createElement("li");
    li.textContent = item;
    return li;
  }));
}

function renderTests(id, items) {
  const target = byId(id);
  target.replaceChildren(...items.map(item => {
    const article = document.createElement("article");
    article.className = "test-item";
    const heading = document.createElement("div");
    const name = document.createElement("h4");
    name.textContent = item.name;
    heading.append(name);
    if (item.status) {
      const status = document.createElement("span");
      status.className = "test-status";
      status.textContent = item.status;
      heading.append(status);
    }
    const prediction = document.createElement("p");
    prediction.textContent = item.prediction || item.test;
    article.append(heading, prediction);
    if (item.weakener) {
      const weakener = document.createElement("p");
      weakener.className = "test-weakener";
      weakener.textContent = `Would weaken it: ${item.weakener}`;
      article.append(weakener);
    }
    return article;
  }));
}

function renderCoverageForm() {
  byId("coverage-scope").value = state.coverage.scope || "";
  byId("coverage-cutoff").value = state.coverage.cutoff || "";
  byId("coverage-stop").value = state.coverage.stop || "";
  byId("coverage-gaps").value = state.coverage.gaps || "";
}

function renderCoverageSummary() {
  const target = byId("report-coverage");
  const rows = [
    ["Provenance", ({
      registry: "Built-in reviewed dossier",
      automatic: "Live automatic Source Sweep receipt",
      "automatic-running": "Live Source Sweep still running",
      user: "User-entered boundary · not independently authenticated",
      restored: "Restored local boundary · execution unverified"
    })[state.coverageOrigin] || "Not recorded."],
    ["Searched", state.coverage.scope || "Not recorded."],
    ["Cutoff", state.coverage.cutoff || "Not recorded."],
    ["Stop rule", state.coverage.stop || "Not recorded."],
    ["Unsearched / inaccessible", state.coverage.gaps || "Not recorded."]
  ];
  target.replaceChildren(...rows.map(([label, value]) => {
    const row = document.createElement("div");
    const heading = document.createElement("strong");
    heading.textContent = label;
    const body = document.createElement("p");
    body.textContent = value;
    row.append(heading, body);
    return row;
  }));
}

function researchFailureCount(run = state.research) {
  return (run?.queries || []).filter(item => item.status === "failed" || item.status === "cancelled").length;
}

function currentMissingEvidenceGaps() {
  if (!state.map) return [];
  if (state.curated) return state.map.gaps || [];
  const parentRecord = trustedParentRecord();
  const inherited = parentRecord ? [
    `This is an unreviewed user fork of ${parentRecord.caseId} version ${parentRecord.version}. The reviewed parent record remains unchanged.`,
    ...(parentRecord.gaps || []).map(item => `Inherited parent-record gap: ${item}`)
  ] : [];
  const includeInherited = items => [...inherited, ...items];
  if (state.research?.queries?.length) {
    if (state.research.status === "running") {
      const answered = state.research.queries.filter(item => item.status !== "pending").length;
      return includeInherited([
        `Source Sweep is still running: ${answered} of ${state.research.queries.length} planned lanes have answered. This is not a completed coverage record.`,
        "Pending provider lanes, underlying records, full text, datasets, media originals, funding declarations, and chains of custody remain untested.",
        "Do not export, save, or draw an absence conclusion until the sweep finishes or is explicitly stopped."
      ]);
    }
    if (state.research.status === "restored-unverified") {
      const failures = researchFailureCount(state.research);
      return includeInherited([
        `A local browser snapshot lists ${state.research.queries.length} planned metadata lanes; ${failures} are recorded as failed or cancelled. Browser storage cannot authenticate that the listed execution occurred.`,
        "Restored provider names, endpoints, and queries were reset to the fixed Source Sweep plan; returned titles and counts remain unverified local data.",
        "No underlying full text, dataset, media original, archive file, funding declaration, or chain of custody was automatically inspected.",
        "General web pages, unindexed material, later result pages, other languages, private, deleted, sealed, classified, paywalled, and undiscovered records remain outside the listed sweep."
      ]);
    }
    const failures = researchFailureCount(state.research);
    return includeInherited([
      `The automatic sweep queried ${state.research.queries.length} predeclared metadata lanes; ${failures} failed or were cancelled. Returned metadata has not been authenticated.`,
      "No underlying full text, dataset, media original, archive file, funding declaration, or chain of custody was automatically inspected.",
      "General web pages, unindexed material, later result pages, non-English variants, private, deleted, sealed, classified, paywalled, and undiscovered records remain outside this bounded sweep.",
      "A zero-result query is not evidence that the claim is false; absence requires an explicit expected-record and preservation test."
    ]);
  }
  if (Object.values(state.coverage || {}).some(Boolean)) {
    if (parentRecord && state.coverageOrigin === "registry") {
      return includeInherited([
        `The displayed search boundary is inherited from the immutable reviewed parent dossier; no additional search has been recorded for this fork.`,
        "Any evidence or interpretation added in this fork remains unreviewed and does not change the parent finding."
      ]);
    }
    return includeInherited([
      "A user-entered or locally restored search boundary is recorded, but Trust-Worthy has not independently verified that the named searches were completed as described.",
      `Declared scope: ${state.coverage.scope || "Not recorded."}`,
      `Declared inaccessible or unsearched evidence: ${state.coverage.gaps || "Not recorded."}`,
      "No automatic provider execution receipt exists for this manual coverage record; source contents, dependencies, incentives, and custody still require inspection."
    ]);
  }
  return includeInherited(state.map.gaps || []);
}

function coverageFromResearch(run) {
  const completed = (run.queries || []).filter(item => item.status === "complete");
  const failed = (run.queries || []).filter(item => item.status !== "complete");
  const lanes = (run.queries || []).map(item => `${item.provider} ${item.stance} (${item.status})`).join("; ");
  const failedNames = failed.length ? ` Failed or cancelled lanes: ${failed.map(item => `${item.provider} ${item.stance}: ${item.error || item.status}`).join("; ")}.` : " All six bounded lanes returned a response.";
  return {
    scope: `Automatic Source Sweep execution receipt: ${lanes}. Exact encoded queries, UTC timestamps, result counts, and errors are preserved in the search receipt.`,
    cutoff: run.completedAt || new Date().toISOString(),
    stop: `Bounded automatic stop after requesting the top 5 metadata hits from each of ${run.queries.length} predeclared lanes; no pagination or full-text inspection.`,
    gaps: `${failedNames} Search ranking, unindexed pages, general web results, other languages, paywalled text, deleted, private, sealed, classified, and undiscovered records remain outside this sweep. ${completed.length} lanes completed; a zero-result lane is never evidence that the claim is false.`
  };
}

function renderResearchQueries() {
  const run = state.research || emptyResearchRun();
  nodes.researchQueries.replaceChildren();
  if (!run.queries.length) {
    const empty = document.createElement("p");
    empty.className = "empty-state";
    empty.textContent = "Run the sweep to create an execution receipt.";
    nodes.researchQueries.append(empty);
    return;
  }
  run.queries.forEach(item => {
    const row = document.createElement("article");
    row.className = "query-row";
    const source = document.createElement("div");
    const provider = document.createElement("strong");
    provider.textContent = item.provider;
    const repository = document.createElement("span");
    repository.textContent = `${item.stance} lane · ${item.repository}`;
    source.append(provider, repository);
    const queryBlock = document.createElement("div");
    const query = document.createElement("code");
    query.textContent = item.query;
    queryBlock.append(query);
    if (item.error) {
      const error = document.createElement("small");
      error.textContent = item.error;
      queryBlock.append(error);
    }
    const status = document.createElement("span");
    status.className = `query-state ${item.status || "pending"}`;
    status.textContent = item.status === "complete" ? `${item.resultCount} returned` : item.status || "pending";
    row.append(source, queryBlock, status);
    nodes.researchQueries.append(row);
  });
}

function renderResearchResults() {
  const results = [...(state.research?.results || [])];
  const stanceOrder = { challenge: 0, neutral: 1, support: 2 };
  results.sort((a, b) => (stanceOrder[a.stance] ?? 9) - (stanceOrder[b.stance] ?? 9));
  nodes.researchResults.replaceChildren();
  if (!results.length) {
    const empty = document.createElement("p");
    empty.className = "empty-state";
    empty.textContent = state.research?.status === "running" ? "Waiting for provider responses…" : "No automated leads were returned. Zero results do not establish that the claim is false.";
    nodes.researchResults.append(empty);
    return;
  }
  results.forEach(result => {
    const article = document.createElement("article");
    article.className = "research-lead";
    const topline = document.createElement("div");
    topline.className = "lead-topline";
    const source = document.createElement("span");
    source.textContent = (result.foundBy || [result.provider]).join(" + ");
    const stance = document.createElement("span");
    const stances = result.queryStances || [result.stance];
    stance.className = `lead-stance-${stances.includes("challenge") ? "challenge" : result.stance}`;
    stance.textContent = `${stances.join(" + ")} query lane${stances.length === 1 ? "" : "s"}`;
    topline.append(source, stance);
    const title = document.createElement("h4");
    title.textContent = result.title;
    const meta = document.createElement("p");
    meta.className = "lead-meta";
    meta.textContent = [result.author, result.publisher, result.date, result.kind].filter(Boolean).join(" · ") || "Creator and date not returned by the index";
    article.append(topline, title, meta);
    if (result.snippet) {
      const snippet = document.createElement("p");
      snippet.textContent = result.snippet;
      article.append(snippet);
    }
    const warning = document.createElement("p");
    warning.className = "lead-warning";
    warning.textContent = "Metadata lead only. The underlying record, quoted passage, custody, funding, and source lineage have not been inspected.";
    article.append(warning);
    const variants = Array.isArray(result.variants) && result.variants.length ? result.variants : [result];
    const lineage = document.createElement("p");
    lineage.className = "lead-lineage";
    if (result.familyRelationship === "merged-by-identity") {
      lineage.textContent = `${variants.length} metadata variants were grouped only because they share a stable identifier or normalized canonical URL. Inspect every variant and the merge receipt.`;
    } else if (result.possibleDuplicateClusters?.length) {
      lineage.textContent = "Possible title duplicate retained separately: a similar title alone does not establish a shared artifact or independent corroboration.";
    } else {
      lineage.textContent = "Single metadata record; no identity-based merge was made.";
    }
    article.append(lineage);
    const actions = document.createElement("div");
    actions.className = "lead-actions";
    const variantUrls = [...new Set(variants.map(item => safeHttpUrl(item.url)).filter(Boolean))];
    variantUrls.forEach((safe, index) => {
      const link = document.createElement("a");
      link.className = "secondary-action";
      link.href = safe;
      link.target = "_blank";
      link.rel = "noopener noreferrer";
      link.textContent = variantUrls.length === 1 ? "Inspect source ↗" : `Inspect variant ${index + 1} ↗`;
      actions.append(link);
    });
    const attach = document.createElement("button");
    attach.type = "button";
    attach.className = "quiet-action";
    attach.dataset.attachResearch = result.id;
    attach.textContent = "Attach as unreviewed lead";
    attach.setAttribute("aria-label", `Attach as unreviewed lead: ${result.title}`);
    actions.append(attach);
    article.append(actions);
    nodes.researchResults.append(article);
  });
}

function renderResearch() {
  const run = state.research || emptyResearchRun();
  const total = run.queries.length || window.TrustResearch?.buildResearchPlan(state.claim || "placeholder claim")?.length || 6;
  const finished = (run.queries || []).filter(item => ["complete", "failed", "cancelled"].includes(item.status)).length;
  const failures = researchFailureCount(run);
  const running = run.status === "running";
  const status = byId("research-status");
  status.classList.toggle("is-running", running);
  status.classList.toggle("has-failures", !running && failures > 0);
  const statusCode = status.querySelector("strong");
  const statusText = status.querySelector("span");
  if (running) {
    statusCode.textContent = "SEARCHING";
    statusText.textContent = `${finished} of ${total} query lanes answered. No returned title is being treated as proof.`;
  } else if (run.status === "restored-unverified") {
    statusCode.textContent = "RESTORED · EXECUTION UNVERIFIED";
    statusText.textContent = `${run.results.length} locally restored discovery families; browser storage cannot prove the recorded provider requests occurred. Re-run the sweep for a live execution receipt.`;
  } else if (run.status === "complete" || run.status === "complete-with-gaps") {
    statusCode.textContent = failures ? "COMPLETE WITH GAPS" : "BOUNDED SWEEP COMPLETE";
    statusText.textContent = `${run.results.length} identity-bounded evidence families discovered; title-only similarities remain separate. ${failures} provider lane${failures === 1 ? "" : "s"} failed or stopped. Every hit remains unreviewed.`;
  } else {
    statusCode.textContent = "READY";
    statusText.textContent = "No external query has been sent.";
  }
  byId("research-query-count").textContent = `${finished}/${total}`;
  byId("research-result-count").textContent = String(run.results.length);
  byId("research-failure-count").textContent = String(failures);
  byId("cancel-research").hidden = !running;
  byId("copy-search-receipt").disabled = !run.queries.length || running;
  updateClaimEditorGate();
  renderResearchQueries();
  renderResearchResults();
}

function evidenceFromResearchLead(result) {
  const providers = (result.foundBy || [result.provider]).join(", ");
  const variants = Array.isArray(result.variants) && result.variants.length ? result.variants : [result];
  const duplicateNote = result.possibleDuplicateClusters?.length ? " A title-similar record was retained as a separate possible duplicate because no stable shared identity was established." : "";
  return {
    title: result.title,
    url: safeHttpUrl(result.url),
    target: `Claim on trial: ${state.claim}`.slice(0, 180),
    role: "context",
    className: "lead",
    author: result.author || result.publisher || "Not returned by index",
    date: result.date || "Not returned by index",
    checked: `Metadata returned ${state.research.completedAt || new Date().toISOString()}`,
    origin: `Discovery metadata returned by ${providers}; ${variants.length} catalog variant${variants.length === 1 ? " was" : "s were"} retained. The underlying artifact has not been opened or authenticated by Trust-Worthy.${duplicateNote}`,
    independence: "Index independence is not evidence independence. The root record, dataset, citations, institutions, funding, and copied assertions have not been traced.",
    incentives: "Author, publisher, index, funder, uploader, and investigator interests remain unreviewed. Visibility, funding, reputation, ideology, policy, and commercial gain may exist on every side.",
    custody: "Only catalog metadata and an outbound URL were returned. No source bytes were preserved or hashed, and versions, edits, redirects, omissions, and transformation history remain unknown.",
    falsifier: "Discard or downgrade this lead if the link fails, the original metadata conflicts, the content does not address the named claim, or its assertions trace to the same unsupported upstream source.",
    notes: "Automated discovery lead only. Its title or metadata matched a predeclared query; the content has not been inspected and establishes no part of the claim yet."
  };
}

function renderWording(flags, vague) {
  const target = byId("report-wording");
  target.replaceChildren();
  const combined = [...new Set([...flags, ...vague])];
  if (!combined.length) {
    const note = document.createElement("p");
    note.className = "clean-note";
    note.textContent = "No obvious absolute or vague trigger word was detected. Scope, definitions, provenance, and a failure test are still required.";
    target.append(note);
    return;
  }
  const group = document.createElement("div");
  group.className = "word-flags";
  combined.forEach(word => {
    const span = document.createElement("span");
    span.className = "word-flag";
    span.textContent = word;
    group.append(span);
  });
  const note = document.createElement("p");
  note.textContent = "These words may expand the burden of proof or hide an undefined subject. They are warnings, not automatic errors.";
  target.append(group, note);
}

function setTags(labels, curated) {
  const target = byId("report-classification");
  const tags = [...labels, curated ? "reviewed dossier · artifact limits shown" : "user record · not independently authenticated"];
  target.replaceChildren(...tags.map(label => {
    const span = document.createElement("span");
    span.className = `tag${label.includes("not independently") ? " risk-tag" : ""}`;
    span.textContent = label;
    return span;
  }));
}

function renderCase({ preserveClaimEditor = false } = {}) {
  const map = state.map;
  if (!map) return;
  const reviewed = currentReviewedCase();
  if (!preserveClaimEditor) {
    nodes.input.value = state.claim;
    nodes.count.textContent = String(state.claim.length);
  }
  byId("report-claim").textContent = state.claim;
  setTags(map.labels, state.curated);
  renderWording(map.flags || [], map.vague || []);
  addList("report-clarify", map.clarify);
  addList("report-support", map.support);
  addList("report-counter", map.counter);
  addList("report-sources", map.sources);
  renderTests("report-hypotheses", map.hypotheses || []);
  addList("report-incentives", map.incentives || []);
  renderTests("report-forensics", map.forensics || []);
  addList("report-gaps", currentMissingEvidenceGaps());
  byId("result-kicker").textContent = state.curated ? `Adversarial case ${state.caseId} · version ${state.recordVersion || reviewed?.version}` : state.parent ? `User fork of ${state.parent.caseId} · version ${state.parent.version}` : "Trust-Worthy pre-research map";
  byId("results-title").textContent = state.curated ? "The strongest case—and what still resists it." : "The claim, separated from the conclusion.";
  byId("finding-label").textContent = state.curated ? map.finding : state.evidence.length ? "USER RECORD" : "PRE-RESEARCH";
  byId("finding-summary").textContent = state.curated ? map.summary : state.evidence.length ? "Sources have been entered by the user but have not been independently reviewed by Trust-Worthy." : "No source-backed finding has been made.";
  byId("finding-band").classList.toggle("is-supported", false);
  renderResearch();
  renderCoverageSummary();
  renderEvidence();
  updateBoundary();
  updateDeepDiveLink();
  updateFacebookShareLink();
  renderArchivedReceipt();
  lastReport = buildPlainReport();
  void updateReceipt();
  nodes.results.hidden = false;
}

function receiptPayload() {
  return stableStringify({
    schema: "trust-worthy-adversarial-record-v5",
    canonicalization: "recursive lexical object-key order · UTF-8",
    case_id: state.caseId || null,
    curated: state.curated,
    version: state.recordVersion || null,
    reviewed: state.reviewedAt || null,
    parent_record: state.parent ? { ...state.parent } : null,
    claim: state.claim,
    classification: state.map?.labels || [],
    finding: byId("finding-label").textContent,
    finding_summary: byId("finding-summary").textContent,
    wording: {
      absolute_terms: state.map?.flags || [],
      vague_terms: state.map?.vague || []
    },
    reasoning: {
      clarify_before_research: state.map?.clarify || [],
      evidence_burden: state.map?.support || [],
      strongest_counter_test: state.map?.counter || [],
      source_order: state.map?.sources || [],
      hypotheses: state.map?.hypotheses || [],
      interest_and_dependency_ledger: state.map?.incentives || [],
      visual_and_media_tests: state.map?.forensics || [],
      missing_evidence: currentMissingEvidenceGaps()
    },
    search_coverage: { ...state.coverage, provenance: state.coverageOrigin || "none" },
    automated_source_sweep: {
      schema: state.research?.schema || "trust-worthy-source-sweep-v1",
      status: state.research?.status || "idle",
      started_at: state.research?.startedAt || "",
      completed_at: state.research?.completedAt || "",
      queries: (state.research?.queries || []).map(item => ({
        id: item.id,
        provider: item.provider,
        repository: item.repository,
        stance: item.stance,
        query: item.query,
        endpoint: item.url,
        requested_at: item.requestedAt || "",
        completed_at: item.completedAt || "",
        status: item.status || "pending",
        result_count: Number(item.resultCount || 0),
        error: item.error || ""
      })),
      discovered_evidence_families: (state.research?.results || []).map(item => ({
        id: item.id,
        family_id: item.familyId || item.id,
        family_relationship: item.familyRelationship || "single-record",
        providers: item.foundBy || [item.provider],
        query_ids: item.queryIds || [item.queryId],
        stance: item.stance,
        title: item.title,
        url: canonicalUrl(item.url),
        author: item.author || "",
        date: item.date || "",
        publisher: item.publisher || "",
        kind: item.kind || "",
        external_id: item.externalId || "",
        query_stances: item.queryStances || [item.stance],
        snippet: item.snippet || "",
        identity: {
          stable_identifiers: item.identity?.stableIdentifiers || [],
          canonical_urls: (item.identity?.canonicalUrls || []).map(canonicalUrl).filter(Boolean)
        },
        merge_decisions: (item.mergeDecisions || []).map(decision => ({
          action: decision.action,
          left_variant_id: decision.leftVariantId,
          right_variant_id: decision.rightVariantId,
          reason: decision.reason,
          match: decision.match
        })),
        possible_duplicate_clusters: (item.possibleDuplicateClusters || []).map(cluster => ({
          cluster_id: cluster.clusterId,
          basis: cluster.basis,
          decision: cluster.decision,
          reason: cluster.reason,
          member_family_ids: cluster.memberFamilyIds || []
        })),
        variants: (item.variants || [item]).map(variant => ({
          id: variant.id,
          provider: variant.provider,
          repository: variant.repository,
          query_ids: variant.queryIds || [variant.queryId],
          query_stances: variant.queryStances || [variant.stance],
          title: variant.title,
          url: canonicalUrl(variant.url),
          author: variant.author || "",
          date: variant.date || "",
          publisher: variant.publisher || "",
          kind: variant.kind || "",
          external_id: variant.externalId || ""
        })),
        status: "DISCOVERY LEAD · NOT INSPECTED EVIDENCE"
      }))
    },
    boundary: {
      verified: byId("verified-text").textContent,
      inference: byId("inference-text").textContent,
      unknown: byId("unknown-text").textContent
    },
    evidence: state.evidence.map(item => ({
      title: item.title,
      url: canonicalUrl(item.url),
      role: item.role,
      className: item.className,
      author: item.author || "",
      date: item.date || "",
      target: item.target || "",
      recorded: recordedAtLabel(item.checked),
      origin: item.origin || "",
      independence: item.independence || "",
      incentives: item.incentives || "",
      custody: item.custody || "",
      falsifier: item.falsifier || "",
      notes: item.notes || ""
    }))
  });
}

function stableStringify(value) {
  const seen = new WeakSet();
  const normalize = item => {
    if (item === null || typeof item !== "object") return item;
    if (seen.has(item)) throw new TypeError("Cannot fingerprint a circular record.");
    seen.add(item);
    if (Array.isArray(item)) {
      const output = item.map(entry => normalize(entry));
      seen.delete(item);
      return output;
    }
    const output = {};
    Object.keys(item).sort().forEach(key => {
      if (item[key] !== undefined) output[key] = normalize(item[key]);
    });
    seen.delete(item);
    return output;
  };
  return JSON.stringify(normalize(value));
}

async function hashReceipt(payload) {
  if (!globalThis.crypto?.subtle || !globalThis.TextEncoder) {
    return "";
  }
  try {
    const bytes = new TextEncoder().encode(payload);
    const digest = await globalThis.crypto.subtle.digest("SHA-256", bytes);
    return Array.from(new Uint8Array(digest)).map(byte => byte.toString(16).padStart(2, "0")).join("");
  } catch { return ""; }
}

async function updateReceipt() {
  const target = byId("receipt-hash");
  const revision = ++receiptRevision;
  const payload = receiptPayload();
  target.textContent = "Generating…";
  const hash = await hashReceipt(payload);
  if (revision !== receiptRevision) return "";
  target.textContent = hash || "Unavailable in this browser";
  byId("print-record-meta").textContent = [
    `Case ID: ${state.caseId || "unsaved local case"}`,
    `Status: ${state.curated ? "reviewed dossier" : "unreviewed user record"}`,
    state.recordVersion ? `Version: ${state.recordVersion}` : "",
    state.reviewedAt ? `Reviewed: ${state.reviewedAt}` : "",
    `SHA-256 fingerprint: ${hash || "unavailable in this browser"}`
  ].filter(Boolean).join(" · ");
  return hash;
}

function renderArchivedReceipt() {
  const archive = state.archive || emptyArchive();
  const panel = byId("archived-receipt");
  panel.hidden = !archive.receipt;
  panel.classList.toggle("has-mismatch", /^MISMATCH/.test(archive.verified || ""));
  byId("archived-receipt-hash").textContent = archive.hash || "No fingerprint was stored with this snapshot";
  byId("archived-receipt-status").textContent = archive.verified || "Saved snapshot loaded; verification pending";
  byId("copy-archived-hash").disabled = !archive.hash;
  byId("copy-archived-json").disabled = !archive.receipt;
}

async function verifyArchivedReceipt() {
  const archive = state.archive || emptyArchive();
  const receipt = archive.receipt;
  const storedHash = archive.hash;
  const revision = ++archiveRevision;
  if (!receipt) { renderArchivedReceipt(); return; }
  if (!storedHash) {
    state.archive.verified = "No saved fingerprint is available; the archived JSON remains copyable.";
    renderArchivedReceipt();
    return;
  }
  state.archive.verified = "Verifying saved fingerprint…";
  renderArchivedReceipt();
  const computed = await hashReceipt(receipt);
  if (revision !== archiveRevision || state.archive?.receipt !== receipt) return;
  state.archive.verified = !computed
    ? "Stored fingerprint present; cryptographic verification is unavailable in this browser."
    : computed === storedHash
      ? "MATCH · archived JSON reproduces the saved fingerprint"
      : "MISMATCH · archived JSON does not reproduce the saved fingerprint";
  renderArchivedReceipt();
}

function renderEvidence() {
  const list = nodes.evidenceList;
  list.replaceChildren();
  byId("evidence-count").textContent = `${state.evidence.length} source${state.evidence.length === 1 ? "" : "s"}`;
  if (!state.evidence.length) {
    const empty = document.createElement("p");
    empty.className = "empty-state";
    empty.textContent = "No evidence attached. Add only sources you have actually inspected.";
    list.append(empty);
  } else {
    state.evidence.forEach((item, index) => {
      const article = document.createElement("article");
      article.className = "source-entry";
      const top = document.createElement("div");
      top.className = "source-entry-top";
      const titleBlock = document.createElement("div");
      const meta = document.createElement("span");
      meta.className = `source-role role-${item.role}`;
      meta.textContent = item.target ? `${item.role} → ${item.target} · ${item.className}` : `${item.role} · ${item.className}`;
      const title = document.createElement("h4");
      title.textContent = item.title;
      titleBlock.append(meta, title);
      const remove = document.createElement("button");
      remove.type = "button";
      remove.className = "remove-source";
      remove.dataset.removeSource = String(index);
      remove.textContent = "Remove";
      remove.setAttribute("aria-label", `Remove source: ${item.title}`);
      top.append(titleBlock, remove);
      const detail = document.createElement("p");
      detail.className = "source-detail";
      detail.textContent = [item.author, item.date, item.origin, recordedAtLabel(item.checked)].filter(Boolean).join(" · ") || "Origin not recorded";
      const notes = document.createElement("p");
      notes.textContent = item.notes || "No evidence note supplied.";
      article.append(top, detail, notes);
      const auditGrid = document.createElement("div");
      auditGrid.className = "source-audit-grid";
      [
        ["Independence / lineage", item.independence],
        ["Interests / incentives", item.incentives],
        ["Custody / transformations", item.custody],
        ["Weakening condition", item.falsifier]
      ].forEach(([label, value]) => {
        const block = document.createElement("div");
        const heading = document.createElement("strong");
        heading.textContent = label;
        const body = document.createElement("p");
        body.textContent = value || "Not recorded.";
        block.append(heading, body);
        auditGrid.append(block);
      });
      article.append(auditGrid);
      const safe = safeHttpUrl(item.url);
      if (safe) {
        const link = document.createElement("a");
        link.href = safe;
        link.rel = "noopener noreferrer";
        link.target = "_blank";
        link.textContent = "Inspect source ↗";
        article.append(link);
      }
      const audits = sourceFlags(item, state.evidence);
      if (audits.length) {
        const auditLine = document.createElement("div");
        auditLine.className = "audit-flags";
        audits.forEach(flag => {
          const badge = document.createElement("span");
          badge.textContent = flag;
          auditLine.append(badge);
        });
        article.append(auditLine);
      }
      list.append(article);
    });
  }
  renderReadiness();
}

function renderReadiness() {
  const metrics = sourceMetrics(state.evidence, state.coverage);
  if (state.research?.status === "restored-unverified") metrics.coverage = false;
  const score = Object.values(metrics).filter(Boolean).length;
  byId("readiness-score").textContent = `${score}/8`;
  byId("readiness-label").textContent = score === 0 ? "No safeguards recorded" : score === 8 ? "Eight fields present · not proof" : `${score} of 8 fields present`;
  Object.entries(metrics).forEach(([key, ready]) => {
    const card = document.querySelector(`[data-check="${key}"]`);
    if (!card) return;
    card.classList.toggle("is-ready", ready);
    card.querySelector("strong").textContent = ready ? "Recorded" : "Missing";
  });
}

function updateBoundary() {
  const map = state.map;
  if (state.curated) {
    byId("verified-text").textContent = map.verified;
    byId("inference-text").textContent = map.inference;
    byId("unknown-text").textContent = map.unknown;
    byId("case-status").textContent = `Adversarial record open · reviewed ${state.reviewedAt || "date not recorded"}`;
    return;
  }
  const count = state.evidence.length;
  byId("verified-text").textContent = count ? `Within this browser record: ${count} source description${count === 1 ? " has" : "s have"} been entered by the user. Their contents and provenance remain unreviewed.` : map.verified;
  byId("inference-text").textContent = count ? "The attached record can now be compared, but Trust-Worthy has not independently inspected it and makes no finding from user labels alone." : map.inference;
  byId("unknown-text").textContent = map.unknown;
  byId("case-status").textContent = count ? "User-built record · unreviewed" : "Record not searched";
}

function updateDeepDiveLink() {
  const offers = [
    { id: "triage-link", name: "$19 Claim Triage" },
    { id: "deep-link", name: "$99 Deep Investigation" },
    { id: "hyper-link", name: "$249 Hyper-Deep Case File" }
  ];
  offers.forEach(offer => {
    const body = [
      `I would like the Project Unveiled ${offer.name}.`,
      "",
      `Claim: ${state.claim}`,
      `Case ID: ${state.caseId || "not saved"}`,
      `Sources already attached: ${state.evidence.length}`,
      `Automatic discovery families: ${state.research?.results?.length || 0}`,
      `Automatic search status: ${state.research?.status || "not run"}`,
      "",
      "Where I encountered this claim:",
      "Decision or question that depends on it:",
      "Deadline:",
      "Sensitivity constraints (do not send secrets or private third-party records):",
      `Privacy and paid-intake terms: ${PUBLIC_APP_URL}/#privacy`,
      "",
      "Robert J. Hayes / Bobsome1 Media + IT",
      "Phone: 912-701-4008"
    ].join("\n");
    byId(offer.id).href = `mailto:thebobsomest1@gmail.com?subject=${encodeURIComponent(`Project Unveiled ${offer.name} request`)}&body=${encodeURIComponent(body)}`;
  });
}

function publicRecordUrl() {
  return state.curated && state.caseId ? `${PUBLIC_APP_URL}/#case=${encodeURIComponent(state.caseId)}` : PUBLIC_APP_URL;
}

function updateFacebookShareLink() {
  const url = publicRecordUrl();
  const link = byId("facebook-share");
  link.href = `https://www.facebook.com/sharer/sharer.php?u=${encodeURIComponent(url)}`;
  link.setAttribute("aria-label", state.curated ? `Share reviewed case ${state.caseId} on Facebook` : "Share the public Evidence Lab on Facebook");
}

function analyze(claim) {
  const clean = boundedText(claim, 800);
  invalidateResearch();
  clearTransientEditors();
  const demoKey = clean.toLowerCase().replace(/[?.!]+$/, "");
  if (MOON_DEMO_QUESTIONS.has(demoKey)) return loadMoonCase();
  if (ATMOSPHERE_DEMO_QUESTIONS.has(demoKey)) return loadAtmosphereCase();
  state = { claim: clean, map: buildClaimMap(clean), evidence: [], coverage: emptyCoverage(), coverageOrigin: "none", research: emptyResearchRun(), curated: false, caseId: null, recordVersion: null, reviewedAt: null, parent: null, archive: emptyArchive() };
  renderCoverageForm();
  renderCase();
  setActiveStage("search");
  nodes.results.focus({ preventScroll: true });
  nodes.results.scrollIntoView({ behavior: "auto", block: "start" });
}

function loadReviewedCase(record) {
  invalidateResearch();
  clearTransientEditors();
  state = {
    claim: record.claim,
    map: { ...record, flags: [], vague: [] },
    evidence: record.evidence.map(item => ({ ...item })),
    coverage: { ...record.coverage },
    coverageOrigin: "registry",
    research: emptyResearchRun(),
    curated: true,
    caseId: record.caseId,
    recordVersion: record.version,
    reviewedAt: record.reviewed,
    parent: null,
    archive: emptyArchive()
  };
  renderCoverageForm();
  renderCase();
  setActiveStage("search");
  nodes.results.focus({ preventScroll: true });
  nodes.results.scrollIntoView({ behavior: "auto", block: "start" });
}

function loadMoonCase() { loadReviewedCase(moonCase); }
function loadAtmosphereCase() { loadReviewedCase(atmosphereCase); }

function buildPlainReport() {
  if (!state.map) return "";
  const map = state.map;
  const lines = [
    "TRUST-WORTHY ADVERSARIAL EVIDENCE REPORT",
    state.curated ? `CASE ${state.caseId} · VERSION ${state.recordVersion} · REVIEWED ${state.reviewedAt}` : state.parent ? `STATUS: USER FORK OF ${state.parent.caseId} VERSION ${state.parent.version} / NOT INDEPENDENTLY REVIEWED` : "STATUS: PRE-RESEARCH / USER RECORD NOT INDEPENDENTLY REVIEWED",
    "",
    "CLAIM ON TRIAL",
    state.claim,
    "",
    `CLASSIFICATION: ${map.labels.join("; ")}`,
    `CURRENT FINDING: ${byId("finding-label").textContent} — ${byId("finding-summary").textContent}`,
    "",
    "DEFINE BEFORE RESEARCH",
    ...map.clarify.map(item => `- ${item}`),
    "",
    "EVIDENCE BURDEN",
    ...map.support.map(item => `- ${item}`),
    "",
    "STRONGEST COUNTER-TEST",
    ...map.counter.map(item => `- ${item}`),
    "",
    "SOURCE ORDER",
    ...map.sources.map((item, index) => `${index + 1}. ${item}`),
    "",
    "COMPETING HYPOTHESES",
    ...(map.hypotheses || []).flatMap(item => [`- ${item.name}: ${item.prediction}`, `  Would weaken it: ${item.weakener}`]),
    "",
    "INTEREST AND DEPENDENCY LEDGER",
    ...(map.incentives || []).map(item => `- ${item}`),
    "",
    "VISUAL / MEDIA FORENSICS",
    ...(map.forensics || []).map(item => `- ${item.name} [${item.status}]: ${item.test}`),
    "",
    "MISSING-EVIDENCE REGISTER",
    ...currentMissingEvidenceGaps().map(item => `- ${item}`),
    "",
    "SEARCH COVERAGE",
    `Provenance: ${state.coverageOrigin || "none"}`,
    `Searched: ${state.coverage.scope || "Not recorded."}`,
    `Cutoff: ${state.coverage.cutoff || "Not recorded."}`,
    `Stop rule: ${state.coverage.stop || "Not recorded."}`,
    `Gaps / exclusions: ${state.coverage.gaps || "Not recorded."}`,
    "",
    "AUTOMATIC SOURCE SWEEP",
    `Status: ${state.research?.status || "idle"}`,
    `Started: ${state.research?.startedAt || "Not run."}`,
    `Completed: ${state.research?.completedAt || "Not completed."}`,
    ...(state.research?.queries || []).map(item => `- ${item.provider} / ${item.stance} / ${item.status}: ${item.query} (${item.resultCount || 0} returned${item.error ? `; ${item.error}` : ""})`),
    `Discovery families: ${state.research?.results?.length || 0}. Every returned item remains a metadata lead until inspected.`,
    ...(state.research?.results || []).flatMap(item => {
      const variants = item.variants || [item];
      return [
        `- ${item.familyId || item.id}: ${item.familyRelationship || "single-record"}; ${variants.length} retained variant${variants.length === 1 ? "" : "s"}${item.possibleDuplicateClusters?.length ? "; title-similar possible duplicate retained separately" : ""}.`,
        ...variants.map(variant => `  ${variant.provider || "Unknown provider"}: ${variant.title} — ${canonicalUrl(variant.url)}`)
      ];
    }),
    "",
    "ATTACHED RECORD"
  ];
  if (!state.evidence.length) lines.push("- No inspected sources attached.");
  state.evidence.forEach((item, index) => {
    lines.push(`${index + 1}. ${item.title} [${item.role}; ${item.className}${item.target ? `; target: ${item.target}` : ""}]`);
    if (item.url) lines.push(`   ${item.url}`);
    lines.push(`   ${item.notes || "No note supplied."}`);
    lines.push(`   Independence / lineage: ${item.independence || "Not recorded."}`);
    lines.push(`   Interests / incentives: ${item.incentives || "Not recorded."}`);
    lines.push(`   Custody / transformations: ${item.custody || "Not recorded."}`);
    lines.push(`   Weakening condition: ${item.falsifier || "Not recorded."}`);
  });
  lines.push("", "BOUNDARY", `Documented in this dossier: ${byId("verified-text").textContent}`, `Best-supported inference: ${byId("inference-text").textContent}`, `Still unknown: ${byId("unknown-text").textContent}`, "", "You be the judge. Project Unveiled presents Trust-Worthy / Bobsome1.");
  return lines.join("\n");
}

function showToast(message) {
  nodes.toast.textContent = message;
  nodes.toast.classList.add("show");
  if (toastTimer !== null) window.clearTimeout?.(toastTimer);
  toastTimer = window.setTimeout(() => {
    nodes.toast.classList.remove("show");
    toastTimer = null;
  }, 2200);
}

function boundedText(value, maxLength) {
  const raw = String(value == null ? "" : value).slice(0, Math.max(maxLength * 2, maxLength));
  return normalizedClaim(raw).slice(0, maxLength).trim();
}

function sanitizedCoverage(value) {
  const input = value && typeof value === "object" ? value : {};
  return {
    scope: boundedText(input.scope, 1800),
    cutoff: boundedText(input.cutoff, 100),
    stop: boundedText(input.stop, 500),
    gaps: boundedText(input.gaps, 1800)
  };
}

function sanitizedEvidence(value) {
  if (!Array.isArray(value)) return [];
  const sanitized = value.map(item => {
    if (!item || typeof item !== "object") return null;
    const title = boundedText(item.title, 240);
    const url = safeHttpUrl(boundedText(item.url, 1200));
    if (!title) return null;
    return {
      title,
      url,
      target: boundedText(item.target, 220),
      role: ["support", "counter", "context"].includes(item.role) ? item.role : "context",
      className: ["primary", "independent", "analysis", "lead"].includes(item.className) ? item.className : "lead",
      author: boundedText(item.author, 220),
      date: boundedText(item.date, 100),
      checked: boundedText(item.checked, 120),
      origin: boundedText(item.origin, 800),
      independence: boundedText(item.independence, 900),
      incentives: boundedText(item.incentives, 900),
      custody: boundedText(item.custody, 900),
      falsifier: boundedText(item.falsifier, 900),
      notes: boundedText(item.notes, 900)
    };
  }).filter(Boolean);
  return sanitized.slice(0, 100);
}

function sanitizedResearch(value, claim) {
  if (!value || typeof value !== "object" || !Array.isArray(value.queries) || !value.queries.length || !window.TrustResearch?.buildResearchPlan) return emptyResearchRun();
  const plan = window.TrustResearch.buildResearchPlan(claim);
  const allowedQueryIds = new Set(plan.map(item => item.id));
  const rawById = new Map(value.queries
    .filter(item => item && typeof item === "object" && allowedQueryIds.has(boundedText(item.id, 100)))
    .slice(0, 20)
    .map(item => [boundedText(item.id, 100), item]));
  const allowedResultStatuses = new Set(["complete", "failed", "cancelled"]);
  const queries = plan.map(expected => {
    const raw = rawById.get(expected.id);
    const interrupted = raw?.status === "pending" || raw?.status === "running";
    const missing = !raw;
    return {
      ...expected,
      requestedAt: boundedText(raw?.requestedAt, 80),
      completedAt: boundedText(raw?.completedAt, 80),
      status: interrupted || missing ? "cancelled" : allowedResultStatuses.has(raw?.status) ? raw.status : "failed",
      resultCount: Math.max(0, Math.min(100, Number(raw?.resultCount) || 0)),
      error: interrupted
        ? "Saved before this query lane completed; treated as cancelled on reopen."
        : missing ? "Lane missing from the restored local receipt." : boundedText(raw?.error, 240)
    };
  });
  const planById = new Map(plan.map(item => [item.id, item]));
  const rawVariants = Array.isArray(value.results) ? value.results.flatMap(item =>
    Array.isArray(item?.variants) && item.variants.length ? item.variants : [item]
  ) : [];
  const restoredVariants = rawVariants.map((item, index) => {
    const title = boundedText(item?.title, 240);
    const url = safeHttpUrl(boundedText(item?.url, 1200));
    const queryIds = (Array.isArray(item?.queryIds) ? item.queryIds : [item?.queryId])
      .map(entry => boundedText(entry, 100)).filter(id => planById.has(id)).slice(0, 12);
    const primaryLane = planById.get(queryIds[0]);
    if (!title || !url || !primaryLane) return null;
    return {
      id: boundedText(item?.id, 120) || `${primaryLane.id}-restored-${index + 1}`,
      providerId: primaryLane.providerId,
      provider: primaryLane.provider,
      repository: primaryLane.repository,
      stance: primaryLane.stance,
      queryId: primaryLane.id,
      query: primaryLane.query,
      title,
      url,
      author: boundedText(item?.author, 220),
      date: boundedText(item?.date, 80),
      publisher: boundedText(item?.publisher, 180),
      kind: boundedText(item?.kind, 100),
      snippet: boundedText(item?.snippet, 500),
      externalId: boundedText(item?.externalId, 220),
      foundBy: [...new Set(queryIds.map(id => planById.get(id).provider))],
      queryIds,
      queryStances: [...new Set(queryIds.map(id => planById.get(id).stance))],
      metadataOnly: true
    };
  }).filter(Boolean).slice(0, 300);
  const results = window.TrustResearch?.mergeEvidenceFamilies
    ? window.TrustResearch.mergeEvidenceFamilies(restoredVariants).slice(0, 100)
    : restoredVariants.slice(0, 100);
  return {
    schema: "trust-worthy-source-sweep-v1",
    status: "restored-unverified",
    startedAt: boundedText(value.startedAt, 80),
    completedAt: boundedText(value.completedAt, 80),
    claim,
    queries,
    results
  };
}

function sanitizedCanonicalReceipt(value) {
  const raw = String(value || "");
  if (!raw || raw.length > 400000) return "";
  try {
    const parsed = JSON.parse(raw);
    if (!parsed || !["trust-worthy-adversarial-record-v4", "trust-worthy-adversarial-record-v5"].includes(parsed.schema)) return "";
    return stableStringify(parsed);
  } catch { return ""; }
}

function sanitizedParent(value) {
  if (!value || typeof value !== "object") return null;
  const caseId = boundedText(value.caseId, 100);
  if (!caseId) return null;
  return { caseId, version: boundedText(value.version, 60), reviewed: boundedText(value.reviewed, 100) || null };
}

function sanitizedSavedCase(value) {
  if (!value || typeof value !== "object") return null;
  const claim = boundedText(value.claim, 800);
  if (!claim) return null;
  return {
    schema: boundedText(value.schema, 80),
    caseId: boundedText(value.caseId, 100),
    claim,
    evidence: sanitizedEvidence(value.evidence),
    coverage: sanitizedCoverage(value.coverage),
    coverageOrigin: ["none", "user", "automatic", "automatic-running", "registry", "restored"].includes(value.coverageOrigin) ? value.coverageOrigin : "restored",
    research: sanitizedResearch(value.research, claim),
    curated: value.curated === true,
    recordVersion: boundedText(value.recordVersion, 60) || null,
    reviewedAt: boundedText(value.reviewedAt, 100) || null,
    parent: sanitizedParent(value.parent),
    canonicalReceipt: sanitizedCanonicalReceipt(value.canonicalReceipt),
    canonicalReceiptHash: /^[a-f0-9]{64}$/i.test(String(value.canonicalReceiptHash || "")) ? String(value.canonicalReceiptHash).toLowerCase() : "",
    savedAt: boundedText(value.savedAt, 100)
  };
}

function trustedRegistryRecordForSaved(saved) {
  const record = saved?.curated ? reviewedCaseById(saved.caseId) : null;
  return record && String(saved.recordVersion) === String(record.version) ? record : null;
}

function readSavedCases() {
  try {
    const raw = localStorage.getItem(STORAGE_KEY);
    if (raw === null) { docketReadIssue = ""; return []; }
    const parsed = JSON.parse(raw);
    if (!Array.isArray(parsed)) { docketReadIssue = "Stored docket data has an invalid format."; return []; }
    docketReadIssue = "";
    return parsed.map(sanitizedSavedCase).filter(Boolean).slice(0, 20);
  } catch {
    docketReadIssue = "Stored docket data is unreadable or browser storage access is blocked.";
    return [];
  }
}

function writeSavedCases(cases) {
  try { localStorage.setItem(STORAGE_KEY, JSON.stringify(cases.slice(0, 20))); return true; }
  catch { showToast("Local saving is blocked in this browser"); return false; }
}

function hasStoredDocketBlob() {
  try { return localStorage.getItem(STORAGE_KEY) !== null; }
  catch { showToast("Local storage cannot be read in this browser"); return null; }
}

function localRecordKey(saved) {
  const input = stableStringify({
    caseId: saved?.caseId || "",
    savedAt: saved?.savedAt || "",
    claim: saved?.claim || "",
    canonicalReceiptHash: saved?.canonicalReceiptHash || ""
  });
  let hash = 2166136261;
  for (let index = 0; index < input.length; index += 1) {
    hash ^= input.charCodeAt(index);
    hash = Math.imul(hash, 16777619);
  }
  return `tw-record-${(hash >>> 0).toString(16).padStart(8, "0")}`;
}

function setDocketBusy(busy) {
  docketMutationPending = busy;
  byId("clear-cases").disabled = busy;
  nodes.savedCases.querySelectorAll?.("button").forEach(button => { button.disabled = busy; });
  updateClaimEditorGate();
}

function createLocalCaseId(cases = []) {
  const token = globalThis.crypto?.randomUUID
    ? globalThis.crypto.randomUUID().replace(/[^a-z0-9-]/gi, "").toUpperCase()
    : `${Date.now().toString(36)}-${Math.random().toString(36).slice(2, 10)}`.toUpperCase();
  const base = `TW-LOCAL-${new Date().toISOString().slice(0, 10)}-${token}`;
  const occupied = new Set(cases.map(item => item?.caseId).filter(Boolean));
  if (!occupied.has(base)) return base;
  let suffix = 2;
  while (occupied.has(`${base}-${String(suffix).padStart(2, "0")}`)) suffix += 1;
  return `${base}-${String(suffix).padStart(2, "0")}`;
}

function renderSavedCases() {
  const cases = readSavedCases();
  byId("docket-count").textContent = String(cases.length);
  nodes.savedCases.replaceChildren();
  if (!cases.length) {
    const empty = document.createElement("p");
    empty.className = "empty-state";
    empty.textContent = docketReadIssue ? `${docketReadIssue} Use “Clear all local cases” to erase the unreadable local value.` : "No saved cases yet.";
    nodes.savedCases.append(empty);
    return;
  }
  cases.forEach(saved => {
    const item = document.createElement("article");
    item.className = "saved-case";
    const trusted = trustedRegistryRecordForSaved(saved);
    const displayRecord = trusted || saved;
    const open = document.createElement("button");
    open.type = "button";
    open.dataset.openCase = localRecordKey(saved);
    open.disabled = docketMutationPending;
    open.setAttribute("aria-label", `Open saved case: ${displayRecord.claim}`);
    const docketId = document.createElement("span");
    docketId.textContent = displayRecord.caseId || "Local case";
    const docketClaim = document.createElement("strong");
    docketClaim.textContent = displayRecord.claim;
    const sourceCount = Array.isArray(displayRecord.evidence) ? displayRecord.evidence.length : 0;
    const docketMeta = document.createElement("small");
    docketMeta.textContent = `${sourceCount} source${sourceCount === 1 ? "" : "s"}${trusted ? ` · registry v${trusted.version}` : " · local copy · unreviewed"}`;
    open.append(docketId, docketClaim, docketMeta);
    const remove = document.createElement("button");
    remove.type = "button";
    remove.className = "delete-case";
    remove.dataset.deleteCase = localRecordKey(saved);
    remove.disabled = docketMutationPending;
    remove.textContent = "Delete";
    remove.setAttribute("aria-label", `Delete saved case: ${displayRecord.claim}`);
    item.append(open, remove);
    nodes.savedCases.append(item);
  });
}

function resetCase() {
  invalidateResearch();
  receiptRevision += 1;
  lastReport = "";
  archiveRevision += 1;
  state = { claim: "", map: null, evidence: [], coverage: emptyCoverage(), coverageOrigin: "none", research: emptyResearchRun(), curated: false, caseId: null, recordVersion: null, reviewedAt: null, parent: null, archive: emptyArchive() };
  clearTransientEditors();
  renderCoverageForm();
  nodes.input.value = "";
  nodes.count.textContent = "0";
  nodes.error.textContent = "";
  byId("report-claim").textContent = "";
  ["report-classification", "report-wording", "report-clarify", "report-support", "report-counter", "report-sources", "report-hypotheses", "report-incentives", "report-forensics", "report-gaps"].forEach(id => byId(id).replaceChildren());
  byId("result-kicker").textContent = "Trust-Worthy pre-research map";
  byId("results-title").textContent = "The claim, separated from the conclusion.";
  byId("finding-label").textContent = "PRE-RESEARCH";
  byId("finding-summary").textContent = "No source-backed finding has been made.";
  byId("finding-band").classList.toggle("is-supported", false);
  byId("verified-text").textContent = "Nothing yet. No inspected sources are attached.";
  byId("inference-text").textContent = "";
  byId("unknown-text").textContent = "";
  byId("case-status").textContent = "Record not searched";
  renderResearch();
  renderCoverageSummary();
  renderEvidence();
  nodes.results.hidden = true;
  byId("receipt-hash").textContent = "Build a case to generate a fingerprint";
  byId("print-record-meta").textContent = "";
  renderArchivedReceipt();
  updateDeepDiveLink();
  updateFacebookShareLink();
  updateClaimEditorGate();
  setActiveStage("frame");
  nodes.input.focus();
}

function refreshClaimEditor() {
  if (state.research?.status === "running" && normalizedClaim(nodes.input.value) !== state.claim) {
    interruptResearchForClaimEdit();
  }
  nodes.count.textContent = String(nodes.input.value.length);
  const dirty = updateClaimEditorGate();
  nodes.error.textContent = "";
  return dirty;
}

function selectSampleClaim(claim) {
  nodes.input.value = boundedText(claim, 800);
  refreshClaimEditor();
  nodes.input.focus();
}

function clearTransientEditors() {
  [
    "source-title", "source-url", "source-author", "source-date", "source-target", "source-origin",
    "source-independence", "source-incentives", "source-custody", "source-falsifier", "source-notes",
    "coverage-scope", "coverage-cutoff", "coverage-stop", "coverage-gaps"
  ].forEach(id => { byId(id).value = ""; });
  byId("source-role").value = "support";
  byId("source-class").value = "primary";
  byId("evidence-error").textContent = "";
  byId("coverage-error").textContent = "";
}

nodes.input.addEventListener("input", refreshClaimEditor);

document.querySelectorAll(".sample").forEach(button => {
  button.addEventListener("click", () => {
    selectSampleClaim(button.dataset.claim);
  });
});

nodes.form.addEventListener("submit", event => {
  event.preventDefault();
  const claim = boundedText(nodes.input.value, 800);
  if (claim.length < 8) {
    nodes.error.textContent = "Enter a complete claim or question of at least 8 characters.";
    nodes.input.focus();
    return;
  }
  analyze(claim);
});

function forkReviewedRecord() {
  if (!state.curated) return;
  state.parent = { caseId: state.caseId, version: state.recordVersion, reviewed: state.reviewedAt };
  state.curated = false;
  state.caseId = null;
  state.recordVersion = null;
  state.reviewedAt = null;
}

function invalidateResearch() {
  researchRevision += 1;
  if (researchController) researchController.abort();
  researchController = null;
}

function interruptResearchForClaimEdit() {
  if (state.research?.status !== "running") return;
  invalidateResearch();
  state.research.status = "complete-with-gaps";
  state.research.completedAt = new Date().toISOString();
  state.research.queries = state.research.queries.map(item => item.status === "pending"
    ? { ...item, status: "cancelled", completedAt: state.research.completedAt, error: "Claim editor changed before this lane completed." }
    : item);
  state.coverage = coverageFromResearch(state.research);
  state.coverageOrigin = "automatic";
  renderCoverageForm();
  renderCase({ preserveClaimEditor: true });
  showToast("Source Sweep stopped because the claim editor changed");
}

function currentRecordActionBlocked() {
  if (state.research?.status === "running") {
    showToast("Finish or stop the Source Sweep before using this record action");
    return true;
  }
  if (claimEditorDiffers()) {
    showToast("Build the edited claim map before using this record action");
    return true;
  }
  return false;
}

async function startResearchSweep() {
  if (!state.map || !state.claim) {
    showToast("Build a claim map first");
    return;
  }
  if (!window.TrustResearch?.runFederatedSearch) {
    showToast("Source Sweep failed to load");
    return;
  }
  if (claimEditorDiffers()) {
    showToast("Build the edited claim map before searching.");
    return;
  }
  if (state.research?.status === "running") return;
  const now = Date.now();
  if (now - lastResearchStartedAt < 5000) {
    showToast("Wait a few seconds before starting another bounded sweep");
    return;
  }
  lastResearchStartedAt = now;
  forkReviewedRecord();
  const plan = window.TrustResearch.buildResearchPlan(state.claim);
  const revision = ++researchRevision;
  researchController = typeof AbortController === "function" ? new AbortController() : null;
  state.research = {
    schema: "trust-worthy-source-sweep-v1",
    status: "running",
    startedAt: new Date().toISOString(),
    completedAt: "",
    claim: state.claim,
    queries: plan.map(item => ({ ...item, requestedAt: "", completedAt: "", status: "pending", resultCount: 0, error: "" })),
    results: []
  };
  state.coverage = emptyCoverage();
  state.coverageOrigin = "automatic-running";
  renderCase();
  setActiveStage("search");

  try {
    const result = await window.TrustResearch.runFederatedSearch(state.claim, {
      signal: researchController?.signal,
      onProgress(record) {
        if (revision !== researchRevision) return;
        const index = state.research.queries.findIndex(item => item.id === record.id);
        if (index >= 0) state.research.queries[index] = { ...record };
        renderResearch();
        addList("report-gaps", currentMissingEvidenceGaps());
        lastReport = buildPlainReport();
        void updateReceipt();
      }
    });
    if (revision !== researchRevision) return;
    const failures = researchFailureCount(result);
    state.research = { ...result, status: failures ? "complete-with-gaps" : "complete" };
    state.coverage = coverageFromResearch(state.research);
    state.coverageOrigin = "automatic";
    researchController = null;
    renderCoverageForm();
    renderCase();
    showToast(failures ? "Source Sweep finished with visible gaps" : "Bounded Source Sweep complete");
  } catch (error) {
    if (revision !== researchRevision) return;
    state.research.status = "complete-with-gaps";
    state.research.completedAt = new Date().toISOString();
    state.research.queries = state.research.queries.map(item => item.status === "pending" ? { ...item, status: "failed", error: boundedText(error?.message || "Source Sweep failed before completion.", 240) } : item);
    state.coverage = coverageFromResearch(state.research);
    state.coverageOrigin = "automatic";
    researchController = null;
    renderCoverageForm();
    renderCase();
    showToast("Source Sweep stopped with a recorded failure");
  }
}

nodes.coverageForm.addEventListener("submit", event => {
  event.preventDefault();
  if (!state.map) { byId("coverage-error").textContent = "Build a claim map before recording search coverage."; return; }
  if (state.research?.status === "running") { byId("coverage-error").textContent = "Stop or finish the Source Sweep before recording a manual boundary."; return; }
  if (claimEditorDiffers()) { byId("coverage-error").textContent = "Build the edited claim map before recording its search boundary."; return; }
  const coverage = {
    scope: boundedText(byId("coverage-scope").value, 1800),
    cutoff: boundedText(byId("coverage-cutoff").value, 100),
    stop: boundedText(byId("coverage-stop").value, 500),
    gaps: boundedText(byId("coverage-gaps").value, 1800)
  };
  if (coverage.scope.length < 60) { byId("coverage-error").textContent = "Name the repositories and supportive, challenging, and neutral query families actually searched."; return; }
  if (coverage.cutoff.length < 4) { byId("coverage-error").textContent = "Record the search cutoff date."; return; }
  if (coverage.stop.length < 20) { byId("coverage-error").textContent = "Record the stop rule used for this search."; return; }
  if (coverage.gaps.length < 30) { byId("coverage-error").textContent = "Record inaccessible, excluded, or still-unsearched evidence; ‘none’ needs an explanation."; return; }
  forkReviewedRecord();
  state.coverage = coverage;
  state.coverageOrigin = "user";
  byId("coverage-error").textContent = "";
  renderCase();
  showToast("Search boundary recorded as a user entry");
});

nodes.evidenceForm.addEventListener("submit", event => {
  event.preventDefault();
  if (!state.map) {
    byId("evidence-error").textContent = "Build a claim map before attaching evidence.";
    return;
  }
  if (state.research?.status === "running") { byId("evidence-error").textContent = "Stop or finish the Source Sweep before attaching evidence."; return; }
  if (claimEditorDiffers()) { byId("evidence-error").textContent = "Build the edited claim map before attaching evidence."; return; }
  const title = boundedText(byId("source-title").value, 240);
  const rawUrl = boundedText(byId("source-url").value, 1200);
  const url = safeHttpUrl(rawUrl);
  const notes = boundedText(byId("source-notes").value, 900);
  const target = boundedText(byId("source-target").value, 220);
  const origin = boundedText(byId("source-origin").value, 800);
  const independence = boundedText(byId("source-independence").value, 900);
  const incentives = boundedText(byId("source-incentives").value, 900);
  const custody = boundedText(byId("source-custody").value, 900);
  const falsifier = boundedText(byId("source-falsifier").value, 900);
  if (title.length < 4) { byId("evidence-error").textContent = "Name the exact source."; return; }
  if (rawUrl && !url) { byId("evidence-error").textContent = "Use a valid HTTP or HTTPS source URL."; return; }
  if (url && state.evidence.some(item => dedupeUrl(item.url) === dedupeUrl(url))) { byId("evidence-error").textContent = "That source is already attached. Repetition is not corroboration."; return; }
  if (target.length < 4) { byId("evidence-error").textContent = "Name the exact claim, component, or hypothesis this evidence targets."; return; }
  if (origin.length < 8) { byId("evidence-error").textContent = "Record who produced the source and its upstream origin."; return; }
  if (notes.length < 20) { byId("evidence-error").textContent = "Record what the source establishes and one limitation."; return; }
  if (independence.length < 20) { byId("evidence-error").textContent = "Record its information, institutional, funding, or source dependence."; return; }
  if (incentives.length < 20) { byId("evidence-error").textContent = "Record plausible gains and costs for this source or state why they remain unknown."; return; }
  if (custody.length < 20) { byId("evidence-error").textContent = "Record custody and transformations, including what remains unknown."; return; }
  if (falsifier.length < 20) { byId("evidence-error").textContent = "State what would weaken or disqualify this source."; return; }
  forkReviewedRecord();
  const recordedAt = new Date().toISOString();
  state.evidence.push({
    title, url, target,
    role: ["support", "counter", "context"].includes(byId("source-role").value) ? byId("source-role").value : "context",
    className: ["primary", "independent", "analysis", "lead"].includes(byId("source-class").value) ? byId("source-class").value : "lead",
    author: boundedText(byId("source-author")?.value, 220),
    date: boundedText(byId("source-date")?.value, 100),
    checked: url ? `URL recorded ${recordedAt}` : `Entry recorded ${recordedAt}; no public URL`,
    origin,
    independence,
    incentives,
    custody,
    falsifier,
    notes
  });
  nodes.evidenceForm.reset();
  byId("evidence-error").textContent = "";
  renderCase();
  showToast("Evidence attached as an unreviewed user entry");
});

nodes.evidenceList.addEventListener("click", event => {
  const button = event.target.closest("[data-remove-source]");
  if (!button) return;
  if (currentRecordActionBlocked()) return;
  forkReviewedRecord();
  const index = Number(button.dataset.removeSource);
  state.evidence.splice(index, 1);
  renderCase();
  const nextIndex = Math.min(index, Math.max(0, state.evidence.length - 1));
  const next = nodes.evidenceList.querySelector?.(`[data-remove-source="${nextIndex}"]`) || byId("evidence-title");
  next.focus?.();
  showToast("Source removed from this user record");
});

byId("run-research").addEventListener("click", startResearchSweep);

byId("cancel-research").addEventListener("click", () => {
  if (!researchController) return;
  researchController.abort();
  showToast("Stopping the sweep; completed and cancelled lanes will remain in the receipt");
});

byId("copy-search-receipt").addEventListener("click", async () => {
  if (currentRecordActionBlocked()) return;
  if (!state.research?.queries?.length) {
    showToast("Run the Source Sweep first");
    return;
  }
  try {
    await navigator.clipboard.writeText(stableStringify(state.research));
    showToast("Exact search receipt copied");
  } catch {
    showToast("Copy was blocked by this browser");
  }
});

nodes.researchResults.addEventListener("click", event => {
  const button = event.target.closest("[data-attach-research]");
  if (!button) return;
  if (currentRecordActionBlocked()) return;
  const lead = (state.research?.results || []).find(item => item.id === button.dataset.attachResearch);
  if (!lead) return;
  if (state.evidence.some(item => dedupeUrl(item.url) === dedupeUrl(lead.url))) {
    showToast("That evidence family is already attached");
    return;
  }
  forkReviewedRecord();
  state.evidence.push(evidenceFromResearchLead(lead));
  renderCase();
  showToast("Attached as an unreviewed lead—not as proof");
});

byId("save-case").addEventListener("click", async () => {
  if (docketMutationPending) { showToast("A local docket change is already finishing"); return; }
  if (!state.map) { showToast("Build a claim map first"); return; }
  if (claimEditorDiffers()) { showToast("Build the edited claim map before saving."); return; }
  if (state.research?.status === "running") { showToast("Wait for the Source Sweep to finish or stop it before saving."); return; }
  const startingCases = readSavedCases();
  const existedAtStart = Boolean(state.caseId && startingCases.some(item => item.caseId === state.caseId));
  if (!existedAtStart && startingCases.length >= 20) {
    showToast("Local docket is full (20 cases). Delete one before saving another.");
    return;
  }
  const caseId = state.caseId || createLocalCaseId(startingCases);
  state.caseId = caseId;
  updateDeepDiveLink();
  const canonicalReceipt = receiptPayload();
  const startingDocketRevision = docketRevision;
  setDocketBusy(true);
  try {
    const canonicalReceiptHash = await hashReceipt(canonicalReceipt);
    if (startingDocketRevision !== docketRevision) {
      showToast("The local docket changed in another tab. Nothing was saved; review and try again.");
      return;
    }
    if (claimEditorDiffers() || canonicalReceipt !== receiptPayload()) {
      updateClaimEditorGate();
      showToast("The case changed while saving. Nothing was saved; review it and save again.");
      return;
    }
    const cases = readSavedCases();
    const existing = cases.findIndex(item => item.caseId === caseId);
    if (existedAtStart && existing < 0) {
      showToast("This saved case was removed while the fingerprint was generated. It was not restored.");
      return;
    }
    if (existing < 0 && cases.length >= 20) {
      showToast("Local docket is now full (20 cases). Nothing was evicted; delete one and try again.");
      return;
    }
    const snapshot = {
      schema: "trust-worthy-local-snapshot-v5",
      caseId,
      claim: state.claim,
      evidence: JSON.parse(JSON.stringify(state.evidence)),
      coverage: { ...state.coverage },
      coverageOrigin: state.coverageOrigin,
      research: JSON.parse(JSON.stringify(state.research || emptyResearchRun())),
      curated: state.curated,
      recordVersion: state.recordVersion,
      reviewedAt: state.reviewedAt,
      parent: state.parent ? { ...state.parent } : null,
      canonicalReceipt,
      canonicalReceiptHash,
      savedAt: new Date().toISOString()
    };
    if (existing >= 0) cases[existing] = snapshot; else cases.unshift(snapshot);
    if (writeSavedCases(cases)) {
      docketRevision += 1;
      state.archive = { receipt: canonicalReceipt, hash: canonicalReceiptHash, verified: "" };
      renderSavedCases();
      renderCase();
      void verifyArchivedReceipt();
      showToast("Case saved on this device");
    }
  } finally {
    setDocketBusy(false);
  }
});

byId("clear-cases").addEventListener("click", () => {
  if (docketMutationPending) { showToast("Wait for the current local save to finish"); return; }
  const hasStoredData = hasStoredDocketBlob();
  if (hasStoredData === null) return;
  if (!hasStoredData) { showToast("No local cases to clear"); return; }
  if (!window.confirm || window.confirm("Clear every Trust-Worthy case saved in this browser?")) {
    if (writeSavedCases([])) {
      docketRevision += 1;
      renderSavedCases();
      showToast("All local cases cleared");
    }
  }
});

nodes.savedCases.addEventListener("click", event => {
  if (docketMutationPending) { showToast("Wait for the current local save to finish"); return; }
  const open = event.target.closest("[data-open-case]");
  const remove = event.target.closest("[data-delete-case]");
  const cases = readSavedCases();
  if (open) {
    const saved = cases.find(item => localRecordKey(item) === open.dataset.openCase);
    if (!saved) return;
    const trusted = trustedRegistryRecordForSaved(saved);
    if (trusted) {
      loadReviewedCase(trusted);
      state.archive = { receipt: saved.canonicalReceipt, hash: saved.canonicalReceiptHash, verified: "" };
      renderArchivedReceipt();
      void verifyArchivedReceipt();
      showToast("Opened the immutable reviewed case from the built-in registry");
      return;
    }
    invalidateResearch();
    const claimedReviewedParent = saved.curated ? {
      caseId: saved.caseId || "claimed reviewed record",
      version: saved.recordVersion || "unknown",
      reviewed: saved.reviewedAt || null
    } : null;
    const registryParent = trustedParentRecord(claimedReviewedParent || saved.parent);
    const inheritedRegistryCoverage = registryParent && saved.coverageOrigin === "registry";
    state = {
      claim: saved.claim,
      map: registryParent ? { ...registryParent, flags: [], vague: [] } : buildClaimMap(saved.claim),
      evidence: JSON.parse(JSON.stringify(saved.evidence)),
      coverage: inheritedRegistryCoverage ? { ...registryParent.coverage } : { ...saved.coverage },
      coverageOrigin: saved.research?.status === "restored-unverified"
        ? "restored"
        : inheritedRegistryCoverage
          ? "registry"
          : saved.coverageOrigin === "registry" ? "restored" : saved.coverageOrigin || "restored",
      research: JSON.parse(JSON.stringify(saved.research || emptyResearchRun())),
      curated: false,
      caseId: claimedReviewedParent ? null : saved.caseId || null,
      recordVersion: null,
      reviewedAt: null,
      parent: claimedReviewedParent || saved.parent || null,
      archive: { receipt: saved.canonicalReceipt, hash: saved.canonicalReceiptHash, verified: "" }
    };
    clearTransientEditors();
    renderCoverageForm();
    renderCase();
    void verifyArchivedReceipt();
    setActiveStage("search");
    if (claimedReviewedParent) showToast("Local review claims are never trusted; opened as an unreviewed copy");
    nodes.results.focus({ preventScroll: true });
    nodes.results.scrollIntoView({ behavior: "auto", block: "start" });
  }
  if (remove) {
    const index = cases.findIndex(item => localRecordKey(item) === remove.dataset.deleteCase);
    const saved = cases[index];
    if (!saved) return;
    const label = trustedRegistryRecordForSaved(saved)?.claim || saved.claim;
    if (window.confirm && !window.confirm(`Delete the locally saved case “${label}”? This cannot be undone.`)) return;
    cases.splice(index, 1);
    if (writeSavedCases(cases)) {
      docketRevision += 1;
      renderSavedCases();
      showToast("Local case deleted");
      const next = nodes.savedCases.querySelector?.("[data-open-case]") || byId("clear-cases");
      next.focus?.();
    }
  }
});

byId("copy-report").addEventListener("click", async () => {
  if (currentRecordActionBlocked()) return;
  lastReport = buildPlainReport();
  try { await navigator.clipboard.writeText(lastReport); showToast("Evidence report copied"); }
  catch { showToast("Copy was blocked by this browser"); }
});

byId("copy-receipt").addEventListener("click", async () => {
  if (currentRecordActionBlocked()) return;
  const hash = await hashReceipt(receiptPayload());
  if (!hash) { showToast("Fingerprint generation is unavailable in this browser"); return; }
  try { await navigator.clipboard.writeText(hash); showToast("Current record fingerprint copied"); }
  catch { showToast("Copy was blocked by this browser"); }
});

byId("copy-receipt-json").addEventListener("click", async () => {
  if (currentRecordActionBlocked()) return;
  try { await navigator.clipboard.writeText(receiptPayload()); showToast("Canonical receipt JSON copied"); }
  catch { showToast("Copy was blocked by this browser"); }
});

byId("copy-archived-hash").addEventListener("click", async () => {
  const hash = state.archive?.hash;
  if (!hash) { showToast("No archived fingerprint is available"); return; }
  try { await navigator.clipboard.writeText(hash); showToast("Archived save-time fingerprint copied"); }
  catch { showToast("Copy was blocked by this browser"); }
});

byId("copy-archived-json").addEventListener("click", async () => {
  const receipt = state.archive?.receipt;
  if (!receipt) { showToast("No archived receipt is available"); return; }
  try { await navigator.clipboard.writeText(receipt); showToast("Archived save-time JSON copied"); }
  catch { showToast("Copy was blocked by this browser"); }
});

byId("share-report").addEventListener("click", async () => {
  if (currentRecordActionBlocked()) return;
  lastReport = buildPlainReport();
  if (navigator.share) {
    try {
      await navigator.share({ title: "Project Unveiled Evidence Report", text: lastReport, url: publicRecordUrl() });
      return;
    } catch (error) {
      if (error && error.name === "AbortError") return;
    }
  }
  try { await navigator.clipboard.writeText(lastReport); showToast("Share copy placed on your clipboard"); }
  catch { showToast("Sharing was blocked by this browser"); }
});

byId("facebook-share").addEventListener("click", () => {
  showToast(state.curated ? "Opening Facebook with the reviewed public-case URL only" : "Opening Facebook with the public lab URL only");
});

byId("print-report").addEventListener("click", async () => { if (currentRecordActionBlocked()) return; await updateReceipt(); window.print(); });
byId("new-case").addEventListener("click", resetCase);
byId("load-moon-case").addEventListener("click", loadMoonCase);
byId("open-proof-case").addEventListener("click", loadMoonCase);
byId("open-atmosphere-case").addEventListener("click", loadAtmosphereCase);

["triage-link", "deep-link", "hyper-link"].forEach(id => {
  byId(id).addEventListener("click", event => {
    if (!claimEditorDiffers()) return;
    event.preventDefault();
    showToast("Build the edited claim map before opening a paid investigation request");
  });
});

document.querySelectorAll(".stage").forEach(button => {
  button.addEventListener("click", () => {
    if (button.dataset.stage === "frame") { setActiveStage("frame"); nodes.input.focus(); return; }
    if (!state.map) { showToast("Build a claim map first"); return; }
    const targetId = stageTargetId(button.dataset.stage);
    const target = targetId ? byId(targetId) : null;
    if (!target) return;
    setActiveStage(button.dataset.stage);
    target.focus({ preventScroll: true });
    target.scrollIntoView({ behavior: "auto", block: "start" });
  });
});

window.addEventListener?.("storage", event => {
  if (event.key !== STORAGE_KEY) return;
  docketRevision += 1;
  renderSavedCases();
  if (docketMutationPending) showToast("The local docket changed in another tab; the pending save will not overwrite it");
});

window.TrustWorthy = { buildClaimMap, isMoonClaim, isAtmosphereClaim, stageTargetId, safeHttpUrl, canonicalUrl, dedupeUrl, sourceMetrics, sourceFlags, receiptPayload, createLocalCaseId, selectSampleClaim, localRecordKey, publicRecordUrl, moonCase, atmosphereCase };
renderSavedCases();
updateDeepDiveLink();
updateFacebookShareLink();
renderArchivedReceipt();
updateClaimEditorGate();
const deepLinkedCaseId = (() => {
  try { return new URLSearchParams(globalThis.location?.hash?.replace(/^#/, "") || "").get("case"); }
  catch { return null; }
})();
const deepLinkedCase = reviewedCaseById(deepLinkedCaseId);
if (deepLinkedCase) loadReviewedCase(deepLinkedCase);
