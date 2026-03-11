# Lapisense WordPress Client

- Treat the public `Client` API, `README.md` examples, and tests as the package contract.
- Keep this library as a thin WordPress adapter over `lapisense/php-client`; protocol and request logic should stay in the PHP client when possible.
- Preserve support for both plugin and theme consumers. Avoid assumptions that only work for one product type.
- Use unit tests to mock WordPress behavior rather than depending on a live WordPress runtime for ordinary library changes.
- Run tests and quality tools through the container defined in `docker-compose.yml`, not the host environment.
