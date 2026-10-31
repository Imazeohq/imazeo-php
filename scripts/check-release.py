"""Validate the SDK's independent SemVer, version constants and release metadata."""
import os
import re
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
MANIFEST = 'composer.json'
MANIFEST_PATTERN = '"version"\\s*:\\s*"([^"]+)"'
CONSTANT = 'src/Version.php'
CONSTANT_PATTERN = 'SDK_VERSION\\s*=\\s*[\\\'"]([^\\\'"]+)'

version = re.search(MANIFEST_PATTERN, (ROOT / MANIFEST).read_text(), re.MULTILINE).group(1)
constant = re.search(CONSTANT_PATTERN, (ROOT / CONSTANT).read_text()).group(1)
assert re.fullmatch(r"(0|[1-9]\d*)\.(0|[1-9]\d*)\.(0|[1-9]\d*)(?:-[0-9A-Za-z.-]+)?", version), "Invalid SemVer"
assert version == constant, "Package metadata and SDK User-Agent version disagree"
if os.environ.get("GITHUB_REF_TYPE") == "tag":
    assert os.environ.get("GITHUB_REF_NAME") == "v" + version, "Tag and SDK version disagree"
for required in ("LICENSE", "README.md", "GUIDE.md", "CHANGELOG.md"):
    assert (ROOT / required).is_file(), "Missing package document: " + required
assert "MIT License" in (ROOT / "LICENSE").read_text(), "Review SDK licensing"
print("SDK release metadata valid:", version)
