"""Exercise EFB's HTTP submission settings path with an intentionally empty form.

The validation failure creates no entry and sends no emails. No cleanup deletes.
"""
import requests

base = 'https://www.ong-grandcoeur.org'
session = requests.Session()
refresh = session.get(base + '/wp-json/Emsfb/v1/nonce/refresh', timeout=30)
refresh.raise_for_status()
nonce = refresh.json()['nonce']
response = session.post(base + '/wp-json/Emsfb/v1/forms/message/add',
    headers={'X-WP-Nonce': nonce, 'Origin': base},
    json={'id': '5', 'sid': 'efb-settings-validation-only', 'page_id': '0',
          'url': base + '/', 'value': '[]', 'type': 'form'}, timeout=30)
print('HTTP', response.status_code)
print(response.text[:1500])
response.raise_for_status()
payload = response.json()
assert payload.get('data', {}).get('success') is False, 'Empty form must not be saved'
print('PASS: Empty submission still rejected without saving an entry.')
