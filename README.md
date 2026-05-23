## Name
Satellite Status Page and API

## Description
This is a project space for development of the Satellite Status Page and API, hosted at https://www.amsat.org/status.

## Installation
- Copy all contents to a web enabled server.
- Obtain a (test) database with satellite status information from AMSAT.
  -- In the future, we hope to have a production database available for all to use.
- Import database into MySQL/MariaDB server.
- Update the config.php file with your MariaDB/MySQL database host, name, credentials, etc.
- Start MySQL/MariaDB server.
- Start web server.

## Local Docker
Run the app and a seeded MariaDB database locally:

```sh
docker compose up -d --build
```

Then visit:
- App: http://localhost:8080
- API docs: http://localhost:8080/api/
- API example: http://localhost:8080/api/v1/satellites.php
- MariaDB: `localhost:3307`, database `satellite_status`, user `satellite`, password `satellite`

The admin login is `admin` / `password` for local development only.
Stop the stack with:

```sh
docker compose down
```

## Usage
- Visit $SITEURL (configured in config.php) to see the data.
- Utilize the API.

## Support (in order of preference)
1. Create an Issue on the Project's GitLab page
2. Post in AMSAT Discord Server #open-source-dev channel.
3. E-mail IT@amsat.org

## Roadmap
- TBD

## Contributing
- All ideas/contributions are open to discussion. 

## Authors and acknowledgment

## License
- TBD

## Project Status
2022 July 20: The beginning of open/public development for this tool.

***



## GitLab Recommended Steps for New Projects

## Integrate with your tools

- [ ] [Set up project integrations](https://gitlab.amsat.org/open-source/satellite-status/-/settings/integrations)

## Collaborate with your team

- [ ] [Invite team members and collaborators](https://docs.gitlab.com/ee/user/project/members/)
- [ ] [Create a new merge request](https://docs.gitlab.com/ee/user/project/merge_requests/creating_merge_requests.html)
- [ ] [Automatically close issues from merge requests](https://docs.gitlab.com/ee/user/project/issues/managing_issues.html#closing-issues-automatically)
- [ ] [Enable merge request approvals](https://docs.gitlab.com/ee/user/project/merge_requests/approvals/)
- [ ] [Automatically merge when pipeline succeeds](https://docs.gitlab.com/ee/user/project/merge_requests/merge_when_pipeline_succeeds.html)

## Test and Deploy

Use the built-in continuous integration in GitLab.

- [ ] [Get started with GitLab CI/CD](https://docs.gitlab.com/ee/ci/quick_start/index.html)
- [ ] [Analyze your code for known vulnerabilities with Static Application Security Testing(SAST)](https://docs.gitlab.com/ee/user/application_security/sast/)
- [ ] [Deploy to Kubernetes, Amazon EC2, or Amazon ECS using Auto Deploy](https://docs.gitlab.com/ee/topics/autodevops/requirements.html)
- [ ] [Use pull-based deployments for improved Kubernetes management](https://docs.gitlab.com/ee/user/clusters/agent/)
- [ ] [Set up protected environments](https://docs.gitlab.com/ee/ci/environments/protected_environments.html)
