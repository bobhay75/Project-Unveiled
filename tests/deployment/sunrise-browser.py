"""Render the two public entry pages using only files in the cPanel manifest."""
import functools
import html
import os
from pathlib import Path
import shutil
import subprocess
import tempfile
import threading
from http.server import SimpleHTTPRequestHandler, ThreadingHTTPServer

ROOT = Path(__file__).resolve().parents[2]
ART = '/images/project-unveiled-sunrise.webp'
ORIGIN = 'https://bobsome1.com'
CHECK = """
const frame = document.querySelector('iframe');
frame.onload = () => {
  const doc = frame.contentDocument;
  const image = doc.querySelector('.hero-art img');
  const action = doc.querySelector('main a[href], .hero a[href]');
  if (!image || !action) return;
  action.focus();
  const bounds = image.getBoundingClientRect();
  const style = frame.contentWindow.getComputedStyle(image);
  const ok = image.getAttribute('src') === ART && image.complete &&
    image.naturalWidth === 1672 && image.naturalHeight === 941 &&
    bounds.width > 0 && bounds.height > 0 && bounds.left >= -1 &&
    bounds.right <= frame.clientWidth + 1 &&
    Math.abs(bounds.width / bounds.height - image.naturalWidth / image.naturalHeight) < 0.02 &&
    style.objectFit === 'contain' && doc.activeElement === action &&
    doc.documentElement.scrollWidth <= frame.clientWidth + 1 &&
    doc.querySelector('meta[property="og:image"]').content === ORIGIN + ART &&
    doc.querySelector('meta[name="twitter:image"]').content === ORIGIN + ART;
  document.body.dataset.result = ok ? 'pass' : 'fail';
};
"""
class QuietHandler(SimpleHTTPRequestHandler):
    def log_message(self, *args):
        pass


def main():
    chrome = os.getenv('CHROME_BIN') or shutil.which('google-chrome') or shutil.which('chromium')
    if not chrome:
        raise SystemExit('Chrome is required for the sunrise layout check.')
    with tempfile.TemporaryDirectory() as temporary:
        root = Path(temporary) / 'public'
        for name in (ROOT / 'deployment/public-files.txt').read_text().splitlines():
            dest = root / name
            dest.parent.mkdir(parents=True, exist_ok=True)
            shutil.copyfile(ROOT / name, dest)
        handler = functools.partial(QuietHandler, directory=str(root))
        with ThreadingHTTPServer(('127.0.0.1', 0), handler) as server:
            threading.Thread(target=server.serve_forever, daemon=True).start()
            try:
                for width in (360, 1280):
                    for path in ('/', '/book/'):
                        shell = ('<!doctype html><iframe style="border:0;width:%dpx;height:850px"></iframe>'
                                 '<script>const ART="%s", ORIGIN="%s";%s frame.src="%s";</script>'
                                 % (width, ART, ORIGIN, CHECK, html.escape(path)))
                        (root / 'review.html').write_text(shell)
                        result = subprocess.run([
                            chrome, '--headless=new', '--no-sandbox', '--disable-gpu',
                            '--disable-dev-shm-usage', '--disable-background-networking',
                            '--user-data-dir=' + str(Path(temporary) / 'profile'),
                            '--window-size=1440,1000', '--virtual-time-budget=1500', '--dump-dom',
                            'http://127.0.0.1:%d/review.html' % server.server_port,
                        ], check=True, capture_output=True, text=True, timeout=30)
                        if '<body data-result="pass">' not in result.stdout:
                            raise SystemExit(f'Sunrise layout, image or focus failed on {path} at {width}px')
                        print(f'{path} at {width}px: full artwork, focus, page width and social image passed')
            finally:
                server.shutdown()


if __name__ == '__main__':
    main()
