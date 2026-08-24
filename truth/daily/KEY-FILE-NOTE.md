# Trust-Worthy API key storage

On shared hosting, Trust-Worthy may load the OpenAI API key from this private server path when an environment variable is unavailable:

`/home/bobsome1/site-private/trust-worthy/openai-key.txt`

The file must never be committed to GitHub or placed in `public_html`.

Recommended permissions: `600`.
