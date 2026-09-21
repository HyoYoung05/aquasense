"""Map an Arduino CLI build's compiler settings to the original VS Code sketch.

Usage: python tools/refresh_intellisense.py <build-directory>
Reads build metadata only; never reads or copies private configuration headers.
"""

import json
from pathlib import Path
import shlex
import sys


def expand_response_files(arguments, directory):
    for argument in arguments:
        if argument.startswith("@"):
            response = Path(argument[1:])
            if not response.is_absolute():
                response = directory / response
            tokens = shlex.split(response.read_text(encoding="utf-8-sig"), posix=True)
            yield from expand_response_files(tokens, directory)
        else:
            yield argument


def main():
    if len(sys.argv) != 2:
        raise SystemExit("Usage: python tools/refresh_intellisense.py <build-directory>")
    project = Path(__file__).resolve().parent.parent
    sketch = project / "UltrasonicTest" / "UltrasonicTest.ino"
    database = Path(sys.argv[1]).resolve() / "compile_commands.json"
    entries = json.loads(database.read_text(encoding="utf-8-sig"))
    entry = next(item for item in entries
                 if Path(item["file"]).name == "UltrasonicTest.ino.cpp")
    directory = Path(entry["directory"])
    arguments = list(expand_response_files(entry["arguments"], directory))
    compiler = Path(arguments[0])
    if not compiler.is_file():
        compiler = compiler.with_suffix(".exe")
    if not compiler.is_file():
        raise SystemExit("The compiler from this build is no longer installed. Rebuild first.")

    # Expand SDK prefix options into ordinary include paths understood by C/C++.
    # Remove output/dependency options and the generated source file. No temporary
    # response files or build artifacts are needed after this database is written.
    result = [str(compiler)]
    prefix = ""
    core = None
    tokens = iter(arguments[1:])
    for token in tokens:
        if token in ("-o", "-MF", "-MT", "-MQ"):
            next(tokens)
        elif token in ("-c", "-MMD", "-MD", "-MP") or token == entry["file"]:
            continue
        elif token == "-iprefix":
            prefix = next(tokens)
        elif token == "-iwithprefixbefore":
            result.append("-I" + str(Path(prefix) / next(tokens)))
        else:
            result.append(token)
            if token.startswith("-I") and (Path(token[2:]) / "Arduino.h").is_file():
                core = Path(token[2:])
    if core is None:
        raise SystemExit("Arduino.h was not found in the build's include paths. Rebuild first.")
    result.extend(["-x", "c++", "-include", str(core / "Arduino.h"), "-c", str(sketch)])
    output = project / ".vscode" / "compile_commands.local.json"
    output.parent.mkdir(exist_ok=True)
    output.write_text(json.dumps([{
        "directory": str(project),
        "file": str(sketch),
        "arguments": result,
    }], indent=2) + "\n", encoding="utf-8")
    print(f"Updated {output}; original .ino mapped to the installed Arduino compiler.")


if __name__ == "__main__":
    main()
