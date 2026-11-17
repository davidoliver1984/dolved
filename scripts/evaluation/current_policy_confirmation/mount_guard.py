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
    assert bound["api_bind_equivalences"] == API_BIND_EQUIVALENCES
    expected = config["services"]["api"]["volumes"]
    mounts = {m["Destination"]: m for m in api["Mounts"]}
    assert len(mounts) == len(api["Mounts"])
    assert set(API_BIND_EQUIVALENCES) <= set(mounts)
    assert set(mounts) == {m["target"] for m in expected}
    for m in expected:
        actual = mounts[m["target"]]
        assert actual["Type"] == m["type"]
        read_only = m.get("read_only", False)
        assert actual["RW"] == (not read_only)
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
            assert actual["Source"] in allowed
        elif m["type"] == "volume":
            assert actual["Name"] == config["volumes"][m["source"]]["name"]
        else:
            raise ValueError("Unbound worker mount type")
    # The caller performs the existing inside-container runtime-manifest check.
    # A successful spelling check cannot short-circuit a content mismatch.
    verify_mounted()
