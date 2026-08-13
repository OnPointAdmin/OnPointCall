#!/usr/bin/env python3
from pathlib import Path

keys = [
    "SOFT_SCORE_BASE_URL",
    "SOFT_SCORE_CLIENT_ID",
    "SOFT_SCORE_CLIENT_SECRET",
    "RND_BASE_URL",
    "RND_REFRESH_TOKEN",
    "RND_COMPANY_ID",
    "MAIL_MAILER",
    "RESEND_API_KEY",
]

env = {}
for line in Path("/opt/onpointcall/.env").read_text().splitlines():
    if not line or line.startswith("#") or "=" not in line:
        continue
    k, v = line.split("=", 1)
    env[k] = v

for k in keys:
    if k not in env:
        print(f"{k}=[MISSING]")
    elif not env[k].strip():
        print(f"{k}=[EMPTY]")
    else:
        print(f"{k}=[SET]")
