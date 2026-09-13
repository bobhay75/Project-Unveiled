# Trust-Worthy API key storage

On shared hosting, Trust-Worthy may load the OpenAI API key from this private server path when an environment variable is unavailable:

`/home/bobsome1/site-private/trust-worthy/openai-key.txt`

The file must never be committed to GitHub or placed in `public_html`.

Required permissions: `600` (owner read/write only). The Daily Desk rejects symlinks and non-regular files, caps the file at 8 KiB, checks its identity and size across the read, and verifies `0600` both before and after reading it. An inability to prove those conditions stops the investigation. Environment keys use the order documented in `README.md` and take precedence only after any object present at this canonical fallback path passes its safety checks.

```bash
chmod 600 /home/bobsome1/site-private/trust-worthy/openai-key.txt
```
