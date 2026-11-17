-- Create test database and grant permissions
CREATE DATABASE IF NOT EXISTS vending_test;
GRANT ALL ON vending_test.* TO 'vending'@'%';
FLUSH PRIVILEGES;