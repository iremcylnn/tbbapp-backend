-- Runs only on first initialization of the pgdata17 volume.
-- Creates the test database used by the Laravel test suite (phpunit.xml / CI).
-- If the volume already exists, create it manually instead:
--   docker compose exec db17 createdb -U tbbapp tbbapp_laravel_test
CREATE DATABASE tbbapp_laravel_test;
