<?php

/*
 * This file is part of the Novo SGA project.
 *
 * (c) Rogerio Lino <rogeriolino@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Entity;

use Novosga\Entity\Unidade;

/**
 * PainelDisplay
 *
 * Representa um painel (TV) gerado pelo admin. O identificador (id) é usado
 * na URL "painel/{id}" para que o display se auto-configure ao ser aberto,
 * sem precisar digitar nada. As credenciais originais nunca são persistidas:
 * apenas o refresh token obtido uma única vez na criação.
 */
class PainelDisplay implements \JsonSerializable
{
    /**
     * @var string
     */
    private $id;

    /**
     * @var string
     */
    private $nome;

    /**
     * @var Unidade
     */
    private $unidade;

    /**
     * @var array
     */
    private $servicos = [];

    /**
     * @var string
     */
    private $refreshToken;

    /**
     * @var \DateTime
     */
    private $criadoEm;

    public function getId()
    {
        return $this->id;
    }

    public function setId($id)
    {
        $this->id = $id;
        return $this;
    }

    public function getNome()
    {
        return $this->nome;
    }

    public function setNome($nome)
    {
        $this->nome = $nome;
        return $this;
    }

    public function getUnidade()
    {
        return $this->unidade;
    }

    public function setUnidade(Unidade $unidade)
    {
        $this->unidade = $unidade;
        return $this;
    }

    public function getServicos()
    {
        return $this->servicos;
    }

    public function setServicos(array $servicos)
    {
        $this->servicos = $servicos;
        return $this;
    }

    public function getRefreshToken()
    {
        return $this->refreshToken;
    }

    public function setRefreshToken($refreshToken)
    {
        $this->refreshToken = $refreshToken;
        return $this;
    }

    public function getCriadoEm()
    {
        return $this->criadoEm;
    }

    public function setCriadoEm(\DateTime $criadoEm)
    {
        $this->criadoEm = $criadoEm;
        return $this;
    }

    public function jsonSerialize()
    {
        return [
            'id' => $this->id,
            'nome' => $this->nome,
            'unidade' => $this->unidade ? [
                'id' => $this->unidade->getId(),
                'nome' => $this->unidade->getNome(),
            ] : null,
            'servicos' => $this->servicos,
            'criadoEm' => $this->criadoEm ? $this->criadoEm->format('c') : null,
        ];
    }
}
