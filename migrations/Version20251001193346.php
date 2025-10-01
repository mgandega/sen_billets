<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251001193346 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SEQUENCE cart_items_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE SEQUENCE categories_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE SEQUENCE event_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE SEQUENCE order_items_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE SEQUENCE orders_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE SEQUENCE payment_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE SEQUENCE products_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE SEQUENCE ticket_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE SEQUENCE ticket_type_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE SEQUENCE user_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE TABLE cart_items (id INT NOT NULL, user_id INT NOT NULL, ticket_type_id INT NOT NULL, payment_id INT DEFAULT NULL, quantity INT NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_BEF48445A76ED395 ON cart_items (user_id)');
        $this->addSql('CREATE INDEX IDX_BEF48445C980D5C1 ON cart_items (ticket_type_id)');
        $this->addSql('CREATE INDEX IDX_BEF484454C3A3BB ON cart_items (payment_id)');
        $this->addSql('COMMENT ON COLUMN cart_items.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN cart_items.updated_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE TABLE categories (id INT NOT NULL, name VARCHAR(255) NOT NULL, description VARCHAR(500) DEFAULT NULL, slug VARCHAR(100) NOT NULL, image_url VARCHAR(255) DEFAULT NULL, is_active BOOLEAN NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_3AF34668989D9B62 ON categories (slug)');
        $this->addSql('CREATE TABLE event (id INT NOT NULL, organizer_id INT NOT NULL, title VARCHAR(255) NOT NULL, description TEXT NOT NULL, event_date TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, start_time TIME(0) WITHOUT TIME ZONE NOT NULL, end_time TIME(0) WITHOUT TIME ZONE NOT NULL, venue VARCHAR(255) NOT NULL, address VARCHAR(500) NOT NULL, category VARCHAR(100) NOT NULL, image_url VARCHAR(500) DEFAULT NULL, capacity INT NOT NULL, sold_tickets INT NOT NULL, status VARCHAR(50) NOT NULL, tags JSON NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, image VARCHAR(255) DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_3BAE0AA7876C4DDA ON event (organizer_id)');
        $this->addSql('CREATE TABLE order_items (id INT NOT NULL, order_ref_id INT NOT NULL, product_id INT NOT NULL, quantity INT NOT NULL, unit_price NUMERIC(10, 2) NOT NULL, total_price NUMERIC(10, 2) NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_62809DB0E238517C ON order_items (order_ref_id)');
        $this->addSql('CREATE INDEX IDX_62809DB04584665A ON order_items (product_id)');
        $this->addSql('CREATE TABLE orders (id INT NOT NULL, user_id INT NOT NULL, order_number VARCHAR(255) NOT NULL, total_amount NUMERIC(10, 2) NOT NULL, status VARCHAR(50) NOT NULL, shipping_address VARCHAR(255) NOT NULL, shipping_city VARCHAR(100) NOT NULL, shipping_postal_code VARCHAR(10) NOT NULL, shipping_country VARCHAR(100) NOT NULL, payment_method VARCHAR(50) DEFAULT NULL, payment_status VARCHAR(50) DEFAULT NULL, notes TEXT DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_E52FFDEE551F0F81 ON orders (order_number)');
        $this->addSql('CREATE INDEX IDX_E52FFDEEA76ED395 ON orders (user_id)');
        $this->addSql('CREATE TABLE payment (id INT NOT NULL, user_id INT NOT NULL, amount DOUBLE PRECISION NOT NULL, status VARCHAR(50) NOT NULL, method VARCHAR(50) NOT NULL, invoice_path VARCHAR(255) DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, paydunya_token VARCHAR(255) DEFAULT NULL, tickets_path VARCHAR(255) DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_6D28840DA76ED395 ON payment (user_id)');
        $this->addSql('COMMENT ON COLUMN payment.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE TABLE products (id INT NOT NULL, name VARCHAR(255) NOT NULL, description TEXT DEFAULT NULL, price NUMERIC(10, 2) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE TABLE ticket (id INT NOT NULL, event_id INT NOT NULL, user_id INT NOT NULL, ticket_type_id INT NOT NULL, payment_id INT DEFAULT NULL, validated_by_id INT DEFAULT NULL, qr_code VARCHAR(255) NOT NULL, purchased_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, status VARCHAR(50) NOT NULL, customer_name VARCHAR(255) NOT NULL, customer_email VARCHAR(255) NOT NULL, used_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, is_used BOOLEAN NOT NULL, pdf_path VARCHAR(255) DEFAULT NULL, qr_code_image_path VARCHAR(255) DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_97A0ADA37D8B1FB5 ON ticket (qr_code)');
        $this->addSql('CREATE INDEX IDX_97A0ADA371F7E88B ON ticket (event_id)');
        $this->addSql('CREATE INDEX IDX_97A0ADA3A76ED395 ON ticket (user_id)');
        $this->addSql('CREATE INDEX IDX_97A0ADA3C980D5C1 ON ticket (ticket_type_id)');
        $this->addSql('CREATE INDEX IDX_97A0ADA34C3A3BB ON ticket (payment_id)');
        $this->addSql('CREATE INDEX IDX_97A0ADA3C69DE5E5 ON ticket (validated_by_id)');
        $this->addSql('CREATE TABLE ticket_type (id INT NOT NULL, event_id INT NOT NULL, name VARCHAR(255) NOT NULL, price INT NOT NULL, quantity INT NOT NULL, sold INT NOT NULL, description TEXT DEFAULT NULL, early_bird BOOLEAN NOT NULL, end_date TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_BE05421171F7E88B ON ticket_type (event_id)');
        $this->addSql('CREATE TABLE "user" (id INT NOT NULL, email VARCHAR(180) NOT NULL, roles JSON NOT NULL, password VARCHAR(255) NOT NULL, name VARCHAR(255) NOT NULL, phone VARCHAR(20) DEFAULT NULL, avatar VARCHAR(255) DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, is_verified BOOLEAN NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_8D93D649E7927C74 ON "user" (email)');
        $this->addSql('ALTER TABLE cart_items ADD CONSTRAINT FK_BEF48445A76ED395 FOREIGN KEY (user_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE cart_items ADD CONSTRAINT FK_BEF48445C980D5C1 FOREIGN KEY (ticket_type_id) REFERENCES ticket_type (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE cart_items ADD CONSTRAINT FK_BEF484454C3A3BB FOREIGN KEY (payment_id) REFERENCES payment (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE event ADD CONSTRAINT FK_3BAE0AA7876C4DDA FOREIGN KEY (organizer_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE order_items ADD CONSTRAINT FK_62809DB0E238517C FOREIGN KEY (order_ref_id) REFERENCES orders (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE order_items ADD CONSTRAINT FK_62809DB04584665A FOREIGN KEY (product_id) REFERENCES products (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE orders ADD CONSTRAINT FK_E52FFDEEA76ED395 FOREIGN KEY (user_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE payment ADD CONSTRAINT FK_6D28840DA76ED395 FOREIGN KEY (user_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE ticket ADD CONSTRAINT FK_97A0ADA371F7E88B FOREIGN KEY (event_id) REFERENCES event (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE ticket ADD CONSTRAINT FK_97A0ADA3A76ED395 FOREIGN KEY (user_id) REFERENCES "user" (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE ticket ADD CONSTRAINT FK_97A0ADA3C980D5C1 FOREIGN KEY (ticket_type_id) REFERENCES ticket_type (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE ticket ADD CONSTRAINT FK_97A0ADA34C3A3BB FOREIGN KEY (payment_id) REFERENCES payment (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE ticket ADD CONSTRAINT FK_97A0ADA3C69DE5E5 FOREIGN KEY (validated_by_id) REFERENCES "user" (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE ticket_type ADD CONSTRAINT FK_BE05421171F7E88B FOREIGN KEY (event_id) REFERENCES event (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('DROP SEQUENCE cart_items_id_seq CASCADE');
        $this->addSql('DROP SEQUENCE categories_id_seq CASCADE');
        $this->addSql('DROP SEQUENCE event_id_seq CASCADE');
        $this->addSql('DROP SEQUENCE order_items_id_seq CASCADE');
        $this->addSql('DROP SEQUENCE orders_id_seq CASCADE');
        $this->addSql('DROP SEQUENCE payment_id_seq CASCADE');
        $this->addSql('DROP SEQUENCE products_id_seq CASCADE');
        $this->addSql('DROP SEQUENCE ticket_id_seq CASCADE');
        $this->addSql('DROP SEQUENCE ticket_type_id_seq CASCADE');
        $this->addSql('DROP SEQUENCE user_id_seq CASCADE');
        $this->addSql('ALTER TABLE cart_items DROP CONSTRAINT FK_BEF48445A76ED395');
        $this->addSql('ALTER TABLE cart_items DROP CONSTRAINT FK_BEF48445C980D5C1');
        $this->addSql('ALTER TABLE cart_items DROP CONSTRAINT FK_BEF484454C3A3BB');
        $this->addSql('ALTER TABLE event DROP CONSTRAINT FK_3BAE0AA7876C4DDA');
        $this->addSql('ALTER TABLE order_items DROP CONSTRAINT FK_62809DB0E238517C');
        $this->addSql('ALTER TABLE order_items DROP CONSTRAINT FK_62809DB04584665A');
        $this->addSql('ALTER TABLE orders DROP CONSTRAINT FK_E52FFDEEA76ED395');
        $this->addSql('ALTER TABLE payment DROP CONSTRAINT FK_6D28840DA76ED395');
        $this->addSql('ALTER TABLE ticket DROP CONSTRAINT FK_97A0ADA371F7E88B');
        $this->addSql('ALTER TABLE ticket DROP CONSTRAINT FK_97A0ADA3A76ED395');
        $this->addSql('ALTER TABLE ticket DROP CONSTRAINT FK_97A0ADA3C980D5C1');
        $this->addSql('ALTER TABLE ticket DROP CONSTRAINT FK_97A0ADA34C3A3BB');
        $this->addSql('ALTER TABLE ticket DROP CONSTRAINT FK_97A0ADA3C69DE5E5');
        $this->addSql('ALTER TABLE ticket_type DROP CONSTRAINT FK_BE05421171F7E88B');
        $this->addSql('DROP TABLE cart_items');
        $this->addSql('DROP TABLE categories');
        $this->addSql('DROP TABLE event');
        $this->addSql('DROP TABLE order_items');
        $this->addSql('DROP TABLE orders');
        $this->addSql('DROP TABLE payment');
        $this->addSql('DROP TABLE products');
        $this->addSql('DROP TABLE ticket');
        $this->addSql('DROP TABLE ticket_type');
        $this->addSql('DROP TABLE "user"');
    }
}
