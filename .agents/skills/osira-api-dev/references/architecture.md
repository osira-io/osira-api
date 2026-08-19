# Architecture

Use this reference when deciding where code belongs.

## Repository shape

Place classes under `src/<TechnicalType>/<Domain>/...` and keep namespaces aligned with paths.

Main technical roots in this repository:

- `Entity`
- `Repository`
- `Service`
- `Dto`
- `State/Provider`
- `State/Processor`
- `Security`
- `Command`
- `DataFixtures`
- `EventSubscriber`
- `Validator`

## Placement rules

- New API resources and transport contracts belong in `Dto/<Domain>/`.
- Reads belong in `State/Provider/<Domain>/`.
- Writes belong in `State/Processor/<Domain>/`.
- Domain/application logic belongs in `Service/<Domain>/`.
- Entity factories that are justified by repository convention belong in `Service/<Domain>/Factory/`.
- Persistence logic stays in Doctrine repositories when query-specific.

## Static enforcement

- `make architecture` is mandatory.
- `tools/check-architecture.php` is intentionally narrow: keep it robust and static.
- Add new architecture checks only when they are durable and low-noise.
