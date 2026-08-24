# Trust-Worthy Immediate Research Setup

This adapter is disabled by default. It never creates a model request unless both private files exist outside public_html and outside Git:

- site-private/trust-worthy/gemini-api-key.txt — one Gemini API key, one line.
- site-private/trust-worthy/research-enabled.txt — the single word enabled.

The adapter uses Gemini web grounding and stores returned source metadata with the private question record. It only displays a provisional answer when two or more web sources were returned. Otherwise it reports that sufficient evidence was not found.

Before enabling: create a Google billing budget/alert. Search grounding can incur use beyond a free allowance.