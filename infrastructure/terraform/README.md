# Terraform Infrastructure

This directory contains the Infrastructure as Code for the production platform.

Environment definitions:

- development
- staging
- production

Reusable components are stored in `modules/`.

Terraform is intentionally introduced after the local Docker environment has
been established.