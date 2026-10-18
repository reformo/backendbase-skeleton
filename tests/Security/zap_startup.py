"""Verify ZAP startup beside a disposable API listener inside the scanner container."""

import http.server
import json
from pathlib import Path
import subprocess
import sys
import threading

port = int(sys.argv[1])
if port not in (8080, 8081):
    raise ValueError('Use port 8081 for the regression or 8080 for conflict diagnosis.')
directory = Path('/zap/wrk')
requests = []


class Handler(http.server.BaseHTTPRequestHandler):
    def do_GET(self):
        requests.append(self.path)
        self.send_response(200)
        self.send_header('Content-Type', 'application/json')
        self.send_header('X-Content-Type-Options', 'nosniff')
        self.end_headers()
        self.wfile.write(b'{"status":"ok"}')

    def log_message(self, *args):
        pass


server = http.server.ThreadingHTTPServer(('127.0.0.1', port), Handler)
threading.Thread(target=server.serve_forever, daemon=True).start()
specification = directory / f'startup-{port}.json'
specification.write_text(json.dumps({
    'openapi': '3.0.3',
    'info': {'title': 'Disposable startup fixture', 'version': '1.0.0'},
    'servers': [{'url': f'http://127.0.0.1:{port}'}],
    'paths': {'/health': {'get': {'operationId': 'health', 'responses': {'200': {'description': 'Ready'}}}}},
}))
report = directory / f'startup-{port}-report.json'
with (directory / f'startup-{port}.log').open('w') as log:
    result = subprocess.run([
        'python3', '/zap/zap-api-scan.py', '-t', str(specification), '-f', 'openapi',
        '-a', '-S', '-T', '1', '-J', report.name,
    ], stdout=log, stderr=subprocess.STDOUT, timeout=180)
server.shutdown()
print(json.dumps({
    'applicationPort': port, 'scannerExit': result.returncode,
    'reportCreated': report.exists(), 'fixtureRequests': len(requests),
}), flush=True)
if port == 8081:
    # This fixture checks startup. Exit 2 reports passive warnings from the dummy server.
    assert result.returncode in (0, 2) and report.exists() and requests, 'Scanner startup regression failed.'
