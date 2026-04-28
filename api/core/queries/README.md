# SQL Query Repository System

## Overview
This system centralizes all SQL queries to remove inline SQL from API files, improving code organization and maintainability.

## Architecture

### QueryRepository.php
A unified wrapper that works with both MySQLi and PDO connections, providing a consistent interface.

### Query Classes
Each entity has its own query class containing all SQL queries:
- `AgentQueries.php` - All agent-related queries
- `WorkerQueries.php` - All worker-related queries
- `SubagentQueries.php` - All subagent-related queries
- `UserQueries.php` - All user-related queries
- `AccountingQueries.php` - All accounting/financial transaction queries

## Usage Example

### Before (Inline SQL):
```php
$stmt = $conn->prepare("SELECT * FROM agents WHERE id = ?");
$stmt->bind_param('i', $id);
$stmt->execute();
$agent = $stmt->get_result()->fetch_assoc();
```

### After (Using Query Repository):
```php
require_once __DIR__ . '/../core/QueryRepository.php';
require_once __DIR__ . '/../core/queries/AgentQueries.php';

$queryRepo = new QueryRepository($conn);
$query = AgentQueries::getById($id);
$agent = $queryRepo->fetchOne($query['sql'], $query['params']);
```

## Benefits

1. **Separation of Concerns**: SQL is separated from business logic
2. **Reusability**: Queries can be reused across multiple API files
3. **Maintainability**: Changes to queries only need to be made in one place
4. **Testability**: Easier to test queries independently
5. **Consistency**: Unified interface for both MySQLi and PDO

## Migration Guide

1. Identify all SQL queries in an API file
2. Move queries to appropriate Query class
3. Replace inline SQL with Query class calls
4. Use QueryRepository to execute queries

## Next Steps

- Create Query classes for remaining entities (HR, Contacts, Notifications, etc.)
- Refactor all API files to use the new system
- Add query validation and error handling
- Create Model classes that use Query classes for business logic








