<?php
declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class InitialSchema extends AbstractMigration
{
    /**
     * Baseline migration — documents the schema that was hand-created
     * before Phinx was introduced. Safe to run on existing databases.
     */
    public function change(): void
    {
        // Only create tables if they don't already exist
        // This is the baseline for all future migrations

        if (!$this->hasTable('clients')) {
            $this->table('clients', ['id' => true, 'primary_key' => 'id'])
                ->addColumn('code', 'string', ['limit' => 20, 'null' => false])
                ->addColumn('name', 'string', ['limit' => 120, 'null' => false])
                ->addColumn('email', 'string', ['limit' => 150, 'null' => false])
                ->addColumn('phone', 'string', ['limit' => 30, 'null' => true])
                ->addColumn('company', 'string', ['limit' => 120, 'null' => true])
                ->addColumn('instagram', 'string', ['limit' => 80, 'null' => true])
                ->addColumn('plan_type', 'enum', ['values' => ['video_individual', 'starter', 'growth', 'authority'], 'null' => false, 'default' => 'video_individual'])
                ->addColumn('status', 'enum', ['values' => ['activo', 'inactivo', 'prospecto'], 'null' => false, 'default' => 'prospecto'])
                ->addColumn('notes', 'text', ['null' => true])
                ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
                ->addIndex(['code'], ['unique' => true])
                ->addIndex(['email'], ['unique' => true])
                ->create();
        }

        if (!$this->hasTable('client_profiles')) {
            $this->table('client_profiles', ['id' => true, 'primary_key' => 'id'])
                ->addColumn('client_id', 'integer', ['null' => false])
                ->addColumn('brand_name', 'string', ['limit' => 120, 'null' => true])
                ->addColumn('brand_colors', 'json', ['null' => true])
                ->addColumn('target_audience', 'text', ['null' => true])
                ->addColumn('content_style', 'string', ['limit' => 50, 'null' => true])
                ->addColumn('platforms', 'json', ['null' => true])
                ->addColumn('monthly_video_goal', 'integer', ['null' => true, 'default' => 4])
                ->addColumn('password_hash', 'string', ['limit' => 255, 'null' => true])
                ->addColumn('updated_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP'])
                ->addForeignKey('client_id', 'clients', 'id', ['delete' => 'CASCADE', 'constraint' => 'fk_cp_client'])
                ->create();
        }

        if (!$this->hasTable('auth_tokens')) {
            $this->table('auth_tokens', ['id' => true, 'primary_key' => 'id'])
                ->addColumn('token', 'string', ['limit' => 128, 'null' => false])
                ->addColumn('user_type', 'enum', ['values' => ['admin', 'client'], 'null' => false])
                ->addColumn('user_id', 'integer', ['null' => false])
                ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
                ->addColumn('expires_at', 'timestamp', ['null' => false])
                ->addIndex(['token'], ['unique' => true])
                ->addIndex(['expires_at'])
                ->create();
        }

        if (!$this->hasTable('admins')) {
            $this->table('admins', ['id' => true, 'primary_key' => 'id'])
                ->addColumn('username', 'string', ['limit' => 50, 'null' => false])
                ->addColumn('password_hash', 'string', ['limit' => 255, 'null' => false])
                ->addColumn('name', 'string', ['limit' => 120, 'null' => true])
                ->addColumn('role', 'enum', ['values' => ['superadmin', 'editor'], 'null' => false, 'default' => 'editor'])
                ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
                ->addIndex(['username'], ['unique' => true])
                ->create();
        }

        if (!$this->hasTable('projects')) {
            $this->table('projects', ['id' => true, 'primary_key' => 'id'])
                ->addColumn('client_id', 'integer', ['null' => false])
                ->addColumn('project_code', 'string', ['limit' => 30, 'null' => false])
                ->addColumn('service_type', 'string', ['limit' => 80, 'null' => false])
                ->addColumn('status', 'enum', ['values' => ['cotizacion', 'confirmado', 'en_produccion', 'revision', 'entregado', 'facturado', 'pagado'], 'null' => false, 'default' => 'cotizacion'])
                ->addColumn('title', 'string', ['limit' => 200, 'null' => true])
                ->addColumn('description', 'text', ['null' => true])
                ->addColumn('price_cop', 'decimal', ['precision' => 12, 'scale' => 2, 'null' => false])
                ->addColumn('currency', 'string', ['limit' => 3, 'null' => true, 'default' => 'COP'])
                ->addColumn('start_date', 'date', ['null' => true])
                ->addColumn('deadline', 'date', ['null' => true])
                ->addColumn('delivered_at', 'timestamp', ['null' => true])
                ->addColumn('notes', 'text', ['null' => true])
                ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
                ->addColumn('updated_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP'])
                ->addForeignKey('client_id', 'clients', 'id', ['constraint' => 'fk_proj_client'])
                ->addIndex(['project_code'], ['unique' => true])
                ->addIndex(['client_id'])
                ->addIndex(['status'])
                ->create();
        }

        if (!$this->hasTable('deliverables')) {
            $this->table('deliverables', ['id' => true, 'primary_key' => 'id'])
                ->addColumn('project_id', 'integer', ['null' => false])
                ->addColumn('type', 'enum', ['values' => ['video', 'thumbnail', 'copy', 'brand_asset', 'revision', 'final'], 'null' => false, 'default' => 'video'])
                ->addColumn('title', 'string', ['limit' => 200, 'null' => false])
                ->addColumn('file_url', 'string', ['limit' => 500, 'null' => true])
                ->addColumn('preview_url', 'string', ['limit' => 500, 'null' => true])
                ->addColumn('description', 'text', ['null' => true])
                ->addColumn('version', 'integer', ['null' => true, 'default' => 1])
                ->addColumn('delivered_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
                ->addForeignKey('project_id', 'projects', 'id', ['delete' => 'CASCADE', 'constraint' => 'fk_del_project'])
                ->addIndex(['project_id'])
                ->create();
        }

        if (!$this->hasTable('invoices')) {
            $this->table('invoices', ['id' => true, 'primary_key' => 'id'])
                ->addColumn('client_id', 'integer', ['null' => false])
                ->addColumn('project_id', 'integer', ['null' => true])
                ->addColumn('invoice_number', 'string', ['limit' => 30, 'null' => false])
                ->addColumn('amount_cop', 'decimal', ['precision' => 12, 'scale' => 2, 'null' => false])
                ->addColumn('tax_cop', 'decimal', ['precision' => 12, 'scale' => 2, 'null' => true, 'default' => 0])
                ->addColumn('total_cop', 'decimal', ['precision' => 12, 'scale' => 2, 'null' => false])
                ->addColumn('status', 'enum', ['values' => ['borrador', 'enviada', 'pendiente', 'pagada', 'vencida', 'cancelada'], 'null' => false, 'default' => 'pendiente'])
                ->addColumn('due_date', 'date', ['null' => true])
                ->addColumn('paid_at', 'timestamp', ['null' => true])
                ->addColumn('payment_method', 'string', ['limit' => 50, 'null' => true])
                ->addColumn('payu_reference', 'string', ['limit' => 100, 'null' => true])
                ->addColumn('notes', 'text', ['null' => true])
                ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
                ->addForeignKey('client_id', 'clients', 'id', ['constraint' => 'fk_inv_client'])
                ->addForeignKey('project_id', 'projects', 'id', ['constraint' => 'fk_inv_project'])
                ->addIndex(['invoice_number'], ['unique' => true])
                ->addIndex(['client_id'])
                ->addIndex(['status'])
                ->create();
        }

        if (!$this->hasTable('brand_assets')) {
            $this->table('brand_assets', ['id' => true, 'primary_key' => 'id'])
                ->addColumn('client_id', 'integer', ['null' => false])
                ->addColumn('asset_type', 'enum', ['values' => ['logo', 'color_palette', 'typography', 'guideline', 'template', 'other'], 'null' => false])
                ->addColumn('name', 'string', ['limit' => 200, 'null' => false])
                ->addColumn('file_url', 'string', ['limit' => 500, 'null' => true])
                ->addColumn('description', 'text', ['null' => true])
                ->addColumn('version', 'integer', ['null' => true, 'default' => 1])
                ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
                ->addForeignKey('client_id', 'clients', 'id', ['constraint' => 'fk_ba_client'])
                ->addIndex(['client_id'])
                ->create();
        }
    }
}
