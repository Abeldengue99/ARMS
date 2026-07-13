#!/usr/bin/env python3
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
EXTS = {'.html', '.htm', '.js', '.php', '.css', '.md', '.json'}

def looks_mojibake(s: str) -> bool:
    return 'Ã' in s or 'Â' in s or '\uFFFD' in s or '�' in s or '? ' in s

def try_fix(text: str):
    try:
        # Interpret current text bytes as latin-1 (single byte) then decode as utf-8
        b = text.encode('latin-1', errors='strict')
        fixed = b.decode('utf-8')
        return fixed
    except Exception:
        return None

def main():
    changed = []
    for p in ROOT.rglob('*'):
        if p.is_file() and p.suffix.lower() in EXTS:
            try:
                txt = p.read_text(encoding='utf-8', errors='replace')
            except Exception:
                continue
            if not looks_mojibake(txt):
                continue
            fixed = try_fix(txt)
            if not fixed:
                continue
            # Heuristic: accept fix if it reduces occurrences of 'Ã' or 'Â' or replacement char
            orig_score = txt.count('Ã') + txt.count('Â') + txt.count('\uFFFD') + txt.count('�')
            new_score = fixed.count('Ã') + fixed.count('Â') + fixed.count('\uFFFD') + fixed.count('�')
            if new_score < orig_score:
                bak = p.with_suffix(p.suffix + '.bak2')
                if not bak.exists():
                    p.write_text(txt, encoding='utf-8')
                    p.rename(bak)
                    # write fixed as utf-8
                    p.write_text(fixed, encoding='utf-8')
                else:
                    # If bak exists already, still overwrite with fixed
                    p.write_text(fixed, encoding='utf-8')
                changed.append((str(p), orig_score, new_score))

    print('fixed_count=', len(changed))
    for fp, o, n in changed:
        print(fp, 'orig_bad_chars=', o, 'new_bad_chars=', n)

if __name__ == '__main__':
    main()
