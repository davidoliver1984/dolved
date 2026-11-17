"""Release-bound idle-sleep protection only; never grants execution authority."""
import hashlib
import json
import os
from pathlib import Path
import sys


def command(binding):
    if binding.get('binding_status') != 'EXECUTION_RELEASE':
        raise ValueError('host-awake execution requires final release binding')
    spec = binding['host_awake']
    argv = spec['command']
    if (not isinstance(argv, list) or len(argv) != 3 or argv[2] != 'execute'
            or not all(isinstance(v, str) for v in argv)
            or not all(Path(v).is_absolute() for v in argv[:2])):
        raise ValueError('unbound execution command')
    if set(spec['files']) != {'/usr/bin/caffeinate', *argv[:2]}:
        raise ValueError('incomplete host-awake dependency binding')
    for name, expected in spec['files'].items():
        if hashlib.sha256(Path(name).read_bytes()).hexdigest() != expected:
            raise ValueError('host-awake dependency drift')
    return ['/usr/bin/caffeinate', '-i', '-s', *argv]


def main(path):
    argv = command(json.loads(Path(path).read_text()))
    # Utility mode holds assertions for this process tree's execution lifetime.
    # No timeout reset, loop, retry, power-setting mutation or authority creation.
    os.execv(argv[0], argv)


if __name__ == '__main__':
    main(sys.argv[1])
