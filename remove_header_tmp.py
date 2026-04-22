from pathlib import Path
import re
path = Path(r'c:/xampp/htdocs/Smart-Meal-Management-System-main/kitchen_staff_dashboard.html')
text = path.read_text(encoding='utf-8', errors='ignore')
orig = text
text = re.sub(r'\s*header\.kitchen-header\s*\{[^}]*\}', '', text, flags=re.S)
text = re.sub(r'\s*\.header-container\s*\{[^}]*\}', '', text, flags=re.S)
text = re.sub(r'\s*\.header-actions\s*\{[^}]*\}', '', text, flags=re.S)
text = re.sub(r'\s*\.header-actions\\s*\.btn\s*\{[^}]*\}', '', text, flags=re.S)
text = re.sub(r'<header[^>]*class=\"kitchen-header\"[\s\S]*?</header>', '', text, flags=re.S)
print('changed' if text != orig else 'unchanged')
path.write_text(text, encoding='utf-8')
