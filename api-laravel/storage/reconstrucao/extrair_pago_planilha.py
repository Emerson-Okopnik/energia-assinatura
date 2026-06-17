#!/usr/bin/env python3
"""
Extrai o "Efetivamente pago" da planilha (aba Faturamento Usinas) -> auditoria-pago.csv
(uc_banco, competencia, valor). Resolve mapa de UC (6 casos) e o swap de maio.

Uso:
  python3 extrair_pago_planilha.py "<planilha.csv>" "<fatura-fonte.csv>"
    fatura-fonte.csv: uc,competencia,fatura  (referência p/ detectar o swap)
"""
import csv, re, sys, os

PLAN = sys.argv[1] if len(sys.argv) > 1 else "Controle geral Consorcio.xlsx - Faturamento Usinas.csv"
FATREF = sys.argv[2] if len(sys.argv) > 2 else ""
SAIDA = os.path.join(os.path.dirname(os.path.abspath(__file__)), "auditoria-pago.csv")

MANUAL = {'2208':'113906836','43044':'521206860','47180':'562606800',
          '59098332':'6656137','4189733':'41897333','59244413':'9244413'}
UNI = [('2025-05',12),('2025-06',13),('2025-07',14),('2025-08',15),('2025-09',16),
       ('2025-10',17),('2025-11',18),('2025-12',19),('2026-01',20),('2026-02',21)]
PARES = [('2026-03',22,23),('2026-04',24,25),('2026-05',26,27),('2026-06',28,29),
         ('2026-07',30,31),('2026-08',32,33),('2026-09',34,35)]

def brl(s):
    if not s or not s.strip(): return None
    t = s.replace('R$','').replace(' ','').strip()
    if t in ('-','—'): return 0.0
    if not re.search(r'\d', t): return None
    try: return float(t.replace('.','').replace(',','.'))
    except: return None

# fatura de referência p/ swap
fat = {}
if FATREF and os.path.isfile(FATREF):
    with open(FATREF, encoding='utf-8') as f:
        for row in csv.DictReader(f):
            v_fat = row.get('fatura') or row.get('fatura_energia') or '0'
            fat[(re.sub(r'\D','',row['uc']), row['competencia'][:7])] = float(v_fat)

with open(PLAN, encoding='utf-8') as f:
    rows = list(csv.reader(f))

sem_ref = []
with open(SAIDA, 'w', newline='') as out:
    w = csv.writer(out); w.writerow(['uc','competencia','valor'])
    n = 0; swaps = 0
    for i in range(2, len(rows)):
        r = rows[i]; nome = (r[0] or '').strip()
        if not nome or nome.upper().startswith('USINAS EM PROCESSO'):
            if nome.upper().startswith('USINAS EM PROCESSO'): break
            continue
        uc = re.sub(r'\D','', (r[3] or '')); buc = MANUAL.get(uc, uc)
        if not buc: continue
        for m, idx in UNI:
            if idx < len(r):
                v = brl(r[idx])
                if v is not None: w.writerow([buc, m, f"{v:.2f}"]); n += 1
        for m, cp, cc in PARES:
            a = brl(r[cp]) if cp < len(r) else None
            b = brl(r[cc]) if cc < len(r) else None
            if a is None and b is None: continue
            if a is None: pago = b
            elif b is None: pago = a
            else:
                kf = fat.get((buc, m))
                if kf is not None and abs(b - kf) > abs(a - kf):
                    pago = b; swaps += 1   # coluna "pago" é a fatura -> swap
                else:
                    pago = a
                    if kf is None:
                        sem_ref.append((buc, m))
            w.writerow([buc, m, f"{pago:.2f}"]); n += 1

print(f"Geradas {n} linhas ({swaps} swaps de maio resolvidos): {SAIDA}")
if sem_ref:
    pares_str = ', '.join([f"{uc} {m}" for uc, m in sem_ref])
    print(f"AVISO: {len(sem_ref)} pares sem referência de fatura (coluna 'pago' assumida): {pares_str}")
