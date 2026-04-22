:root {
  --bg: #0b1221;
  --surface: #0f172a;
  --card: #111827;
  --border: #1f2937;
  --text: #e5e7eb;
  --muted: #9ca3af;
  --accent: #22c55e;
  --accent-2: #06b6d4;
  --warning: #f97316;
  --danger: #f43f5e;
}

* {
  box-sizing: border-box;
}

body {
  margin: 0;
  min-height: 100vh;
  font-family: "Be Vietnam Pro", system-ui, -apple-system, sans-serif;
  background: radial-gradient(circle at 20% 20%, #0f172a, #0b1221 35%, #050911 100%);
  color: var(--text);
}

a {
  color: var(--accent-2);
  text-decoration: none;
}

a:hover {
  color: #67e8f9;
}

header.hero {
  position: relative;
  overflow: hidden;
  padding: 32px 24px;
  color: #0b1221;
  background: linear-gradient(135deg, #0ea5e9 0%, #22c55e 60%, #0b1221 100%);
}

header.hero h1 {
  margin: 0 0 8px;
  font-size: 32px;
  font-weight: 700;
}

header.hero p {
  margin: 0;
  color: #0b1221cc;
}

header.hero .accent-chip {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 8px 12px;
  border-radius: 999px;
  background: rgba(255, 255, 255, 0.85);
  color: #0b1221;
  font-weight: 600;
  box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
}

main {
  padding: 24px;
  background: var(--bg);
}

.surface {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: 16px;
  padding: 20px;
  box-shadow: 0 20px 60px rgba(0, 0, 0, 0.35);
}

.section-title {
  margin: 0 0 16px;
  font-size: 18px;
  font-weight: 700;
}

.controls {
  display: grid;
  gap: 16px;
  grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
}

.form-group {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

label {
  color: var(--muted);
  font-size: 14px;
}

input,
select,
button,
textarea {
  font: inherit;
}

input,
select,
textarea {
  width: 100%;
  padding: 12px;
  border-radius: 10px;
  border: 1px solid var(--border);
  background: #0b1625;
  color: var(--text);
}

button {
  cursor: pointer;
  border: 0;
  border-radius: 12px;
  padding: 12px 14px;
  background: linear-gradient(135deg, var(--accent), #16a34a);
  color: #0b1221;
  font-weight: 700;
  box-shadow: 0 15px 40px rgba(34, 197, 94, 0.25);
  transition: transform 120ms ease, box-shadow 120ms ease, opacity 120ms ease;
}

button:disabled {
  opacity: 0.6;
  cursor: not-allowed;
  box-shadow: none;
}

button:hover:not(:disabled) {
  transform: translateY(-1px);
  box-shadow: 0 18px 45px rgba(34, 197, 94, 0.35);
}

button.secondary {
  background: #1f2937;
  color: var(--text);
  box-shadow: none;
  border: 1px solid var(--border);
}

.meal-toggle {
  display: inline-flex;
  gap: 8px;
  background: #0b1625;
  border: 1px solid var(--border);
  padding: 6px;
  border-radius: 12px;
}

.meal-toggle input {
  display: none;
}

.meal-toggle label {
  margin: 0;
  color: var(--muted);
  padding: 10px 14px;
  border-radius: 10px;
  cursor: pointer;
  transition: all 120ms ease;
}

.meal-toggle input:checked + label {
  background: rgba(34, 197, 94, 0.1);
  color: var(--accent);
  border: 1px solid rgba(34, 197, 94, 0.3);
}

.grid-two {
  display: grid;
  gap: 16px;
  grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
}

.card {
  background: var(--card);
  border: 1px solid var(--border);
  border-radius: 14px;
  padding: 16px;
}

.card h3 {
  margin: 0 0 8px;
  font-size: 16px;
}

.subtle {
  color: var(--muted);
  margin: 0;
}

.stat-cards {
  display: grid;
  gap: 12px;
  grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
}

.stat-card {
  background: #0b1625;
  border: 1px solid var(--border);
  border-radius: 12px;
  padding: 14px;
}

.stat-card .label {
  color: var(--muted);
  font-size: 13px;
}

.stat-card .value {
  font-size: 26px;
  font-weight: 700;
}

.stat-card.green {
  border-color: rgba(34, 197, 94, 0.35);
  color: #bbf7d0;
}

.stat-card.blue {
  border-color: rgba(6, 182, 212, 0.35);
  color: #a5f3fc;
}

.stat-card.amber {
  border-color: rgba(249, 115, 22, 0.35);
  color: #fed7aa;
}

.stat-card.red {
  border-color: rgba(244, 63, 94, 0.35);
  color: #fecdd3;
}

.table-wrap {
  background: var(--card);
  border: 1px solid var(--border);
  border-radius: 14px;
  padding: 16px;
  overflow: auto;
}

table {
  width: 100%;
  border-collapse: collapse;
  color: var(--text);
}

thead tr {
  background: #0b1625;
}

thead th {
  text-align: left;
  padding: 10px;
  font-size: 13px;
  color: var(--muted);
  border-bottom: 1px solid var(--border);
}

tbody td {
  padding: 10px;
  border-bottom: 1px solid var(--border);
  font-size: 14px;
}

tbody tr:hover {
  background: rgba(255, 255, 255, 0.02);
}

.pill {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 6px 10px;
  border-radius: 999px;
  font-size: 13px;
  border: 1px solid var(--border);
  background: #0b1625;
  color: var(--muted);
}

.pill.green {
  border-color: rgba(34, 197, 94, 0.35);
  color: #22c55e;
  background: rgba(34, 197, 94, 0.08);
}

.pill.blue {
  border-color: rgba(6, 182, 212, 0.35);
  color: #06b6d4;
  background: rgba(6, 182, 212, 0.08);
}

.pill.amber {
  border-color: rgba(249, 115, 22, 0.35);
  color: #f97316;
  background: rgba(249, 115, 22, 0.08);
}

.pill.red {
  border-color: rgba(244, 63, 94, 0.35);
  color: #f43f5e;
  background: rgba(244, 63, 94, 0.08);
}

.message {
  margin-top: 8px;
  padding: 12px 14px;
  border-radius: 10px;
  border: 1px solid var(--border);
  background: #0b1625;
  color: var(--text);
  display: none;
}

.message.show {
  display: block;
}

.message.success {
  border-color: rgba(34, 197, 94, 0.35);
  background: rgba(34, 197, 94, 0.08);
  color: #bbf7d0;
}

.message.error {
  border-color: rgba(244, 63, 94, 0.35);
  background: rgba(244, 63, 94, 0.08);
  color: #fecdd3;
}

.last-result {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.last-result strong {
  color: var(--muted);
}

.link-row {
  display: flex;
  gap: 10px;
  flex-wrap: wrap;
}

.info-chip {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 10px 12px;
  border-radius: 10px;
  border: 1px solid var(--border);
  background: #0b1625;
  color: var(--muted);
}

@media (max-width: 768px) {
  header.hero {
    padding: 24px 18px;
  }

  header.hero h1 {
    font-size: 26px;
  }

  main {
    padding: 16px;
  }

  .controls {
    grid-template-columns: 1fr;
  }

  .grid-two {
    grid-template-columns: 1fr;
  }
}
