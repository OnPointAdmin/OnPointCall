#!/usr/bin/env python3
from pathlib import Path

ENV_PATH = Path("/opt/onpointcall/.env")

UPDATES = {
    "SOFT_SCORE_BASE_URL": "https://prod.onpointapi.com",
    "SOFT_SCORE_CLIENT_ID": "fvs54jb1QO6NTkTMhREYE5Fa1mNNnvoBPvDKkMUdAP5lKuse",
    "SOFT_SCORE_CLIENT_SECRET": "sKxfeBtR59XACEqIhmT1EhInyV93GgLw",
}

text = ENV_PATH.read_text()
lines = text.splitlines()
seen = set()
out = []

for line in lines:
    if "=" in line and not line.lstrip().startswith("#"):
        key = line.split("=", 1)[0]
        if key in UPDATES:
            out.append(f"{key}={UPDATES[key]}")
            seen.add(key)
            continue
    out.append(line)

for key, value in UPDATES.items():
    if key not in seen:
        out.append(f"{key}={value}")

ENV_PATH.write_text("\n".join(out) + "\n")
print("updated", ", ".join(UPDATES.keys()))
