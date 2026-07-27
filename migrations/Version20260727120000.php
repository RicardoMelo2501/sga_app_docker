<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Migrations\AbstractVersion;
use Doctrine\DBAL\Schema\Schema;

/**
 * Cria a tabela de painéis (TVs) gerados pelo admin.
 */
final class Version20260727120000 extends AbstractVersion
{
    public function getDescription() : string
    {
        return 'Cria a tabela painel_displays';
    }

    public function up(Schema $schema) : void
    {
        if (!$this->existsColumn('painel_displays', 'id')) {
            $this->addSql("CREATE TABLE painel_displays (id VARCHAR(32) NOT NULL, nome VARCHAR(100) NOT NULL, unidade_id INT NOT NULL, servicos JSON NOT NULL COMMENT '(DC2Type:json)', refresh_token VARCHAR(255) NOT NULL, criado_em DATETIME NOT NULL, INDEX IDX_PAINEL_DISPLAYS_UNIDADE (unidade_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");
            $this->addSql("ALTER TABLE painel_displays ADD CONSTRAINT FK_PAINEL_DISPLAYS_UNIDADE FOREIGN KEY (unidade_id) REFERENCES unidades (id)");
        }
    }
}
