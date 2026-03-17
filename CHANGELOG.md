# Changelog

All notable changes to `laravel-flowfield` will be documented in this file.

## 1.0.0 - 2026-03-17

- Initial release
- `#[FlowField]` attribute for declaring aggregate fields
- `HasFlowFields` trait with cache-backed attribute resolution
- `InvalidatesFlowFields` trait with automatic cache invalidation
- Support for `sum`, `count`, `avg`, `min`, `max`, `exists` aggregations
- `withFlowFields()` scope for eager computation
- `orderByFlowField()` scope for sorting by aggregate values
- `flowfield:warm` and `flowfield:flush` artisan commands
- Works with any Laravel cache driver
