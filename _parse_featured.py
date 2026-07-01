import json
with open('e:/wamp64/www/portal_backend/_featured.json') as f:
    data = json.load(f)
courses = data.get('data', [])
print(f'Featured courses count: {len(courses)}')
for c in courses:
    print(f"ID={c['id']} | title={c['title']} | section={c['section']}")
