from pathlib import Path

env = {}
for line in Path("/opt/onpointcall/.env").read_text().splitlines():
    if not line or line.startswith("#") or "=" not in line:
        continue
    k, v = line.split("=", 1)
    env[k] = v

expected_id = "fvs54jb1QO6NTkTMhREYE5Fa1mNNnvoBPvDKkMUdAP5lKuse"
expected_secret = "sKxfeBtR59XACEqIhmT1EhInyV93GgLw"
cid = env.get("SOFT_SCORE_CLIENT_ID", "")
csec = env.get("SOFT_SCORE_CLIENT_SECRET", "")
print("id_match", cid == expected_id)
print("secret_match", csec == expected_secret)
print("id_len", len(cid))
print("secret_len", len(csec))
print("base", env.get("SOFT_SCORE_BASE_URL"))
