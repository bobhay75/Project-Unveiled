import { linkNearbySites } from "./geo.js";

function includesAny(text, words) {
  return words.some((w) => text.includes(w));
}

export function analyzeSiteLocally(site, allSites) {
  const text =
    `${site.name} ${site.type} ${site.notes} ${(site.observedIndicators || []).join(" ")}`.toLowerCase();
  const signals = [];
  const warnings = [];
  const recommendedNextDocumentation = [
    "Take wide context photos facing all cardinal directions.",
    "Take close photos with scale for rock, lithic, ceiling, wall, or floor features.",
    "Record distance and route relationship to creek, spring, waterfall, shelter, ridge, or trail.",
    "Record whether the feature is above, below, or along a travel corridor.",
    "Compare the site against nearby caves, shelters, and rock scatters before drawing conclusions.",
  ];

  if (
    includesAny(text, [
      "flake",
      "flakes",
      "chip",
      "chips",
      "lithic",
      "chert",
      "flint",
      "cortex",
      "percussion",
      "bulb",
    ])
  ) {
    signals.push(
      "Lithic evidence language detected. Document material type, flake platforms, cortex, bulbs of percussion, and scatter distribution.",
    );
  }
  if (
    includesAny(text, ["soot", "charcoal", "burn", "burned", "fire", "ash"])
  ) {
    signals.push(
      "Fire-related words in your notes; origin is unverified. Photograph ceiling or wall staining with scale and note whether staining is protected from rain.",
    );
  }
  if (
    includesAny(text, [
      "uniform",
      "sorted",
      "sorting",
      "spoil",
      "tailing",
      "tailings",
      "debris",
      "dump",
      "pile",
    ])
  ) {
    signals.push(
      "Displaced-material words in your notes; origin is unverified. Record rock-size range, angularity, depth estimate, and source-wall correlation.",
    );
  }
  if (
    includesAny(text, [
      "spring",
      "creek",
      "water",
      "fall",
      "waterfall",
      "wet weather",
      "dripline",
    ])
  ) {
    signals.push(
      "Water relationship detected. Link this record to creek, spring, waterfall, and travel-corridor mapping.",
    );
  }
  if (
    includesAny(text, [
      "airflow",
      "draft",
      "cold air",
      "void",
      "breeze",
      "continuation",
    ])
  ) {
    signals.push(
      "Possible cave-continuation indicator detected. Document airflow location safely without forcing unstable passages.",
    );
  }
  if (
    includesAny(text, [
      "ceiling scar",
      "scars",
      "tool mark",
      "tool marks",
      "pick",
      "pry",
      "wedge",
      "scrape",
    ])
  ) {
    signals.push(
      "Possible surface-modification language detected. Photograph marks at oblique angles and include scale/orientation.",
    );
  }
  if (
    includesAny(text, ["dig", "dug", "excavate", "excavation", "open it up"])
  ) {
    warnings.push(
      "Documentation-first warning: avoid digging or disturbance unless it is legal, safe, permitted, and properly documented.",
    );
  }
  if (includesAny(text, ["burial", "grave", "bones", "human remains"])) {
    warnings.push(
      "Protected-resource warning: do not disturb potential human remains or burial contexts. Stop and contact proper authorities if required by law.",
    );
  }

  const linkedSites = linkNearbySites(site, allSites);
  const confidenceLabel = "Documentation checklist — not AI identification";

  return {
    provider: "local-checklist",
    analyzedAt: new Date().toISOString(),
    summary: signals.length
      ? signals.join(" ")
      : "No matching documentation keywords yet. Add measurable evidence: photos with scale, rock-size ranges, material type, nearest water, slope, elevation, and nearby site relationships.",
    confidenceLabel,
    signals,
    warnings,
    linkedSites,
    recommendedNextDocumentation,
  };
}
