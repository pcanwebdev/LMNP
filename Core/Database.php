<?php
namespace Core;

/**
 * Database class
 * Handles database connections and provides a PDO instance
 */
class Database extends \PDO
{
    /**
     * Database configuration
     */
    protected $config;
    
    /**
     * Constructor
     * 
     * @param array $config Database configuration
     */
    public function __construct($config)
    {
        $this->config = $config;
        
        // Set default configuration if not provided
        $dsn = isset($config['dsn']) ? $config['dsn'] : $this->buildDsn();
        $username = $config['username'] ?? 'root';
        $password = $config['password'] ?? '';
        $options = $config['options'] ?? [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            \PDO::ATTR_EMULATE_PREPARES => false,
            \PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci'
        ];
        
        // Connect to database
        parent::__construct($dsn, $username, $password, $options);
    }
    
    /**
     * Build the DSN string from configuration
     * 
     * @return string The DSN string
     */
    protected function buildDsn()
    {
        $driver = $this->config['driver'] ?? 'pgsql';
        $host = $this->config['host'] ?? 'localhost';
        $port = $this->config['port'] ?? '5432';
        $database = $this->config['database'] ?? 'lmnp_accounting';
        
        // PostgreSQL doesn't use the charset parameter in the DSN
        if ($driver === 'pgsql') {
            return "{$driver}:host={$host};port={$port};dbname={$database}";
        } else {
            $charset = $this->config['charset'] ?? 'utf8mb4';
            return "{$driver}:host={$host};port={$port};dbname={$database};charset={$charset}";
        }
    }
    
    /**
     * Create database tables if they don't exist
     * 
     * @return void
     */
    public function createTables()
    {
        // Create sequences for auto-increment columns
        $this->exec("CREATE SEQUENCE IF NOT EXISTS users_id_seq");
        $this->exec("CREATE SEQUENCE IF NOT EXISTS settings_id_seq");
        $this->exec("CREATE SEQUENCE IF NOT EXISTS properties_id_seq");
        $this->exec("CREATE SEQUENCE IF NOT EXISTS revenues_id_seq");
        $this->exec("CREATE SEQUENCE IF NOT EXISTS expenses_id_seq");
        $this->exec("CREATE SEQUENCE IF NOT EXISTS depreciation_categories_id_seq");
        $this->exec("CREATE SEQUENCE IF NOT EXISTS depreciation_assets_id_seq");
        $this->exec("CREATE SEQUENCE IF NOT EXISTS depreciation_entries_id_seq");
        $this->exec("CREATE SEQUENCE IF NOT EXISTS reports_id_seq");
        
        // Users table
        $this->exec("
            CREATE TABLE IF NOT EXISTS users (
                id INTEGER PRIMARY KEY DEFAULT nextval('users_id_seq'),
                username VARCHAR(50) NOT NULL UNIQUE,
                email VARCHAR(100) NOT NULL UNIQUE,
                password VARCHAR(255) NOT NULL,
                role VARCHAR(20) NOT NULL DEFAULT 'user',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");
        
        // Create updated_at trigger function
        $this->exec("
            CREATE OR REPLACE FUNCTION update_timestamp()
            RETURNS TRIGGER AS $$
            BEGIN
                NEW.updated_at = CURRENT_TIMESTAMP;
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql
        ");
        
        // Add updated_at trigger to users table
        $this->exec("
            DROP TRIGGER IF EXISTS update_users_timestamp ON users;
            CREATE TRIGGER update_users_timestamp
            BEFORE UPDATE ON users
            FOR EACH ROW
            EXECUTE FUNCTION update_timestamp()
        ");
        
        // Settings table
        $this->exec("
            CREATE TABLE IF NOT EXISTS settings (
                id INTEGER PRIMARY KEY DEFAULT nextval('settings_id_seq'),
                user_id INTEGER NOT NULL,
                setting_key VARCHAR(50) NOT NULL,
                setting_value TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE (user_id, setting_key),
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            )
        ");
        
        // Add updated_at trigger to settings table
        $this->exec("
            DROP TRIGGER IF EXISTS update_settings_timestamp ON settings;
            CREATE TRIGGER update_settings_timestamp
            BEFORE UPDATE ON settings
            FOR EACH ROW
            EXECUTE FUNCTION update_timestamp()
        ");
        
        // Properties table
        $this->exec("
            CREATE TABLE IF NOT EXISTS properties (
                id INTEGER PRIMARY KEY DEFAULT nextval('properties_id_seq'),
                user_id INTEGER NOT NULL,
                name VARCHAR(100) NOT NULL,
                address TEXT NOT NULL,
                acquisition_date DATE NOT NULL,
                acquisition_price DECIMAL(12,2) NOT NULL,
                ownership_percentage DECIMAL(5,2) NOT NULL DEFAULT 100.00,
                property_type VARCHAR(50) NOT NULL,
                status VARCHAR(20) NOT NULL DEFAULT 'active',
                notes TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            )
        ");
        
        // Add updated_at trigger to properties table
        $this->exec("
            DROP TRIGGER IF EXISTS update_properties_timestamp ON properties;
            CREATE TRIGGER update_properties_timestamp
            BEFORE UPDATE ON properties
            FOR EACH ROW
            EXECUTE FUNCTION update_timestamp()
        ");
        
        // Revenues table
        $this->exec("
            CREATE TABLE IF NOT EXISTS revenues (
                id INTEGER PRIMARY KEY DEFAULT nextval('revenues_id_seq'),
                property_id INTEGER NOT NULL,
                user_id INTEGER NOT NULL,
                amount DECIMAL(10,2) NOT NULL,
                revenue_date DATE NOT NULL,
                description VARCHAR(255),
                category VARCHAR(50) NOT NULL,
                recurring BOOLEAN DEFAULT FALSE,
                recurring_frequency VARCHAR(20) DEFAULT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (property_id) REFERENCES properties(id) ON DELETE CASCADE,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            )
        ");
        
        // Add updated_at trigger to revenues table
        $this->exec("
            DROP TRIGGER IF EXISTS update_revenues_timestamp ON revenues;
            CREATE TRIGGER update_revenues_timestamp
            BEFORE UPDATE ON revenues
            FOR EACH ROW
            EXECUTE FUNCTION update_timestamp()
        ");
        
        // Expenses table
        $this->exec("
            CREATE TABLE IF NOT EXISTS expenses (
                id INTEGER PRIMARY KEY DEFAULT nextval('expenses_id_seq'),
                property_id INTEGER NOT NULL,
                user_id INTEGER NOT NULL,
                amount DECIMAL(10,2) NOT NULL,
                expense_date DATE NOT NULL,
                description VARCHAR(255),
                category VARCHAR(50) NOT NULL,
                receipt_path VARCHAR(255),
                is_deductible BOOLEAN DEFAULT TRUE,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (property_id) REFERENCES properties(id) ON DELETE CASCADE,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            )
        ");
        
        // Add updated_at trigger to expenses table
        $this->exec("
            DROP TRIGGER IF EXISTS update_expenses_timestamp ON expenses;
            CREATE TRIGGER update_expenses_timestamp
            BEFORE UPDATE ON expenses
            FOR EACH ROW
            EXECUTE FUNCTION update_timestamp()
        ");
        
        // Depreciation categories table
        $this->exec("
            CREATE TABLE IF NOT EXISTS depreciation_categories (
                id INTEGER PRIMARY KEY DEFAULT nextval('depreciation_categories_id_seq'),
                name VARCHAR(100) NOT NULL,
                default_duration INTEGER NOT NULL,
                rate DECIMAL(5,2) NOT NULL,
                category_type VARCHAR(50) NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");
        
        // Add updated_at trigger to depreciation_categories table
        $this->exec("
            DROP TRIGGER IF EXISTS update_depreciation_categories_timestamp ON depreciation_categories;
            CREATE TRIGGER update_depreciation_categories_timestamp
            BEFORE UPDATE ON depreciation_categories
            FOR EACH ROW
            EXECUTE FUNCTION update_timestamp()
        ");
        
        // Check if depreciation_categories table is empty
        $stmt = $this->query("SELECT COUNT(*) FROM depreciation_categories");
        $count = $stmt->fetchColumn();
        
        // Insert default depreciation categories if table is empty
        if ($count == 0) {
            $this->exec("
                INSERT INTO depreciation_categories (name, default_duration, rate, category_type) VALUES
                ('Immeuble', 30, 3.33, 'property'),
                ('Appartement', 25, 4.00, 'property'),
                ('Mobilier', 7, 14.29, 'furniture'),
                ('Électroménager', 5, 20.00, 'furniture'),
                ('Équipement informatique', 3, 33.33, 'furniture'),
                ('Travaux d''amélioration', 10, 10.00, 'improvement')
            ");
        }
        
        // Depreciation assets table
        $this->exec("
            CREATE TABLE IF NOT EXISTS depreciation_assets (
                id INTEGER PRIMARY KEY DEFAULT nextval('depreciation_assets_id_seq'),
                property_id INTEGER NOT NULL,
                user_id INTEGER NOT NULL,
                category_id INTEGER NOT NULL,
                name VARCHAR(100) NOT NULL,
                acquisition_date DATE NOT NULL,
                base_value DECIMAL(10,2) NOT NULL,
                duration INTEGER NOT NULL,
                rate DECIMAL(5,2) NOT NULL,
                status VARCHAR(20) NOT NULL DEFAULT 'active',
                notes TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (property_id) REFERENCES properties(id) ON DELETE CASCADE,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                FOREIGN KEY (category_id) REFERENCES depreciation_categories(id)
            )
        ");
        
        // Add updated_at trigger to depreciation_assets table
        $this->exec("
            DROP TRIGGER IF EXISTS update_depreciation_assets_timestamp ON depreciation_assets;
            CREATE TRIGGER update_depreciation_assets_timestamp
            BEFORE UPDATE ON depreciation_assets
            FOR EACH ROW
            EXECUTE FUNCTION update_timestamp()
        ");
        
        // Depreciation entries table
        $this->exec("
            CREATE TABLE IF NOT EXISTS depreciation_entries (
                id INTEGER PRIMARY KEY DEFAULT nextval('depreciation_entries_id_seq'),
                asset_id INTEGER NOT NULL,
                year INTEGER NOT NULL,
                amount DECIMAL(10,2) NOT NULL,
                accumulated DECIMAL(10,2) NOT NULL,
                remaining DECIMAL(10,2) NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE (asset_id, year),
                FOREIGN KEY (asset_id) REFERENCES depreciation_assets(id) ON DELETE CASCADE
            )
        ");
        
        // Add updated_at trigger to depreciation_entries table
        $this->exec("
            DROP TRIGGER IF EXISTS update_depreciation_entries_timestamp ON depreciation_entries;
            CREATE TRIGGER update_depreciation_entries_timestamp
            BEFORE UPDATE ON depreciation_entries
            FOR EACH ROW
            EXECUTE FUNCTION update_timestamp()
        ");
        
        // Reports table
        $this->exec("
            CREATE TABLE IF NOT EXISTS reports (
                id INTEGER PRIMARY KEY DEFAULT nextval('reports_id_seq'),
                user_id INTEGER NOT NULL,
                report_type VARCHAR(50) NOT NULL,
                fiscal_year INTEGER NOT NULL,
                status VARCHAR(20) NOT NULL DEFAULT 'draft',
                data JSONB,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE (user_id, report_type, fiscal_year),
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            )
        ");
        
        // Add updated_at trigger to reports table
        $this->exec("
            DROP TRIGGER IF EXISTS update_reports_timestamp ON reports;
            CREATE TRIGGER update_reports_timestamp
            BEFORE UPDATE ON reports
            FOR EACH ROW
            EXECUTE FUNCTION update_timestamp()
        ");
    }
}
