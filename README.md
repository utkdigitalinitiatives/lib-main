# Lib-Main

The University of Tennessee Libraries main website ([lib.utk.edu](https://lib.utk.edu)), built on Drupal 11. Managed with Composer, developed locally with DDEV, and deployed to Azure.

- **Repository:** https://github.com/utkdigitalinitiatives/lib-main
- **Infrastructure/Deployment:** https://github.com/utkdigitalinitiatives/lib-main-infra

## Stack

| Layer        | Technology                      |
| ------------ | ------------------------------- |
| CMS          | Drupal 11                       |
| PHP          | 8.3                             |
| Database     | PostgreSQL 16                   |
| Search       | Apache Solr                     |
| Local Dev    | DDEV                            |
| File Storage | Azure Blob Storage (production) |

## Architecture

| Environment | Database              | File Storage       | Search                   |
| ----------- | --------------------- | ------------------ | ------------------------ |
| Local       | PostgreSQL (via DDEV) | Local filesystem   | Local Solr via ddev-solr |
| Production  | PostgreSQL (Azure)    | Azure Blob Storage | Azure-hosted Solr        |

## Getting Started

New to this project? See the [Developer Onboarding Guide](onboarding.md) for step-by-step local setup instructions, custom DDEV commands, and the team's Git workflow.

## Deployment

Production deployments are managed via GitHub Actions in the [lib-main-infra](https://github.com/utkdigitalinitiatives/lib-main-infra) repository.
