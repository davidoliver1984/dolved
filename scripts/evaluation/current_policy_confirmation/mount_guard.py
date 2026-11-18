"""Exact, release-bound Docker Desktop spelling exceptions; no path resolution."""

API_BIND_EQUIVALENCES = {
    "/app": {
        "source": "/Users/davidoliver/Desktop/RAG-current-policy-pilot/apps/api",
        "read_only": False,
    },
    "/contracts": {
        "source": "/Users/davidoliver/Desktop/RAG-current-policy-pilot/contracts",
        "read_only": True,
    },
}


def verify_api_mounts(config, api, bound, verify_mounted):
    """Path spelling never substitutes for configuration or content verification."""
    verify_mounts(build_mount_spec(config, bound), api['Mounts'], verify_mounted)


def build_mount_spec(config, bound, observer_mounts=None):
    """Compile one exact specification; never derive expectations from a container."""
    assert bound["api_bind_equivalences"] == API_BIND_EQUIVALENCES
    expected = config["services"]["api"]["volumes"]
    spec = {}
    for m in expected:
        read_only = m.get("read_only", False)
        row = {'Type':m['type'],'RW':not read_only}
        if m["type"] == "bind":
            exception = API_BIND_EQUIVALENCES.get(m["target"])
            if exception is not None:
                assert m["source"] == exception["source"]
                assert read_only == exception["read_only"]
            elif m["target"] == "/r28-corpus":
                exception = bound["corpus_bind_equivalence"]
                assert m["source"] == exception["source"]
                assert read_only == exception["read_only"]
            allowed = [m["source"]]
            if exception is not None:
                allowed.append("/host_mnt" + exception["source"])
            row['allowed_sources'] = allowed
        elif m["type"] == "volume":
            row['Name'] = config['volumes'][m['source']]['name']
        else:
            raise ValueError("Unbound worker mount type")
        assert m['target'] not in spec
        spec[m['target']] = row
    assert set(API_BIND_EQUIVALENCES) <= set(spec)
    for target,m in (observer_mounts or {}).items():
        assert target in {'/evaluation-observer','/evaluation-observer-config/observer.json','/observer-journal','/observer-health','/rehearsal-lifecycle'}
        assert target not in spec
        assert m['allowed_source_spellings'] == [m['source'],'/host_mnt'+m['source']]
        spec[target]={'Type':'bind','RW':not m['read_only'],'allowed_sources':m['allowed_source_spellings']}
    return spec


def mount_mismatches(spec, actual):
    errors=[];rows={m.get('Destination'):m for m in actual}
    if len(rows)!=len(actual):errors.append('duplicate_mount_destination')
    if set(rows)!=set(spec):errors.append('mount_set_mismatch')
    for target,expected in spec.items():
        m=rows.get(target)
        if m is None:continue
        if m.get('Type')!=expected['Type']:errors.append('mount_type_mismatch')
        if m.get('RW')!=expected['RW']:errors.append('mount_mode_mismatch')
        if expected['Type']=='bind' and m.get('Source') not in expected['allowed_sources']:errors.append('mount_source_mismatch')
        if expected['Type']=='volume' and m.get('Name')!=expected['Name']:errors.append('volume_identity_mismatch')
    return sorted(set(errors))


def verify_mounts(spec, actual, content_check=lambda:None):
    errors=mount_mismatches(spec,actual)
    try:content_check()
    except Exception:
        if not errors:raise
        errors.append('mounted_content_mismatch')
    if errors:raise AssertionError(','.join(errors))
