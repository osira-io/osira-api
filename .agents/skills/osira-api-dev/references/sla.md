# SLA reports

- PostgreSQL Incidents are the availability source of truth; never recalculate SLA from VictoriaMetrics samples.
- Only Incidents raised by an AlertRule with `impactType = availability` count toward downtime. `performance` and `informational` Incidents are ignored entirely by `SlaCalculator`/`SlaReportService`, even if their interval overlaps an availability Incident. `impactType` is a user-set classification on AlertRule (`AlertRuleImpactType`), never derived from the ItemDefinition key.
- Clip intervals to the report period and merge overlaps per Node. A FIRING Incident ends at the report `to` bound.
- When maintenance is excluded, merge targeted enabled MaintenanceWindows and subtract them from both eligibility and incident downtime.
- Resolve direct Nodes plus current NodeGroup members and deduplicate by Node ULID.
- Aggregate multi-node results by eligible node-seconds: `sum(uptimeSeconds) / sum(eligibleSeconds)`. Do not average percentages.
- Custom report bounds are read-only overrides and must not mutate the SLA entity.
- Zero eligible seconds (empty resolved scope, or the whole period excluded by maintenance) is `SlaReportStatus::NO_DATA`, not 100% availability: `availabilityPercentage` and `compliant` are `null`. A Node with zero eligible seconds is excluded from the weighted aggregate (never counted as 100%) but still appears in `nodes[]` with `status = no_data`. A report is `no_data` only when every Node in scope is `no_data`.
