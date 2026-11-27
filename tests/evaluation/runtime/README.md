# Evaluation runtime configurations

This directory owns the provider-free R28, calibration, current-retrieval and
numbered-experiment Compose overlays. They are always layered after the root
`compose.yaml` by the corresponding scripts in `scripts/evaluation/`.

The launchers derive paths from the repository root, pass that root with
`docker compose --project-directory`, and retain each runtime's established
project name. This keeps build contexts, bind mounts and named volumes unchanged
while the specialised configuration remains outside the repository root.

Historical run commands and frozen evidence continue to be reproduced from the
exact recorded revision. Tag `pilot-unified-design-20261125` retains the former
root-level file layout and must not be moved or recreated.
