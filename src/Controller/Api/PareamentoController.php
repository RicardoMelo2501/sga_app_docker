<?php

/*
 * This file is part of the Novo SGA project.
 *
 * (c) Rogerio Lino <rogeriolino@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Controller\Api;

use Psr\Cache\CacheItemPoolInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

/**
 * PareamentoController
 *
 * Endpoint anônimo usado para parear o painel (TV) com um dispositivo móvel
 * via QR Code. O conteúdo transmitido (credenciais/config) é cifrado no
 * celular e só decifrado na TV: este controller apenas retransmite o texto
 * cifrado por um curto período, sem nunca ter acesso à chave.
 *
 * @Route("/api/pareamento")
 */
class PareamentoController extends AbstractController
{
    private const TTL = 300; // 5 minutos
    private const CACHE_PREFIX = 'pareamento_';

    /**
     * Cria uma nova sessão de pareamento.
     *
     * @Route("", methods={"POST"})
     */
    public function criar(CacheItemPoolInterface $cache): JsonResponse
    {
        $id = bin2hex(random_bytes(16));

        $item = $cache->getItem(self::CACHE_PREFIX . $id);
        $item->set(['cifrado' => null]);
        $item->expiresAfter(self::TTL);
        $cache->save($item);

        return $this->json([
            'id' => $id,
            'expiraEm' => self::TTL,
        ]);
    }

    /**
     * Consultado pela TV. Enquanto o celular não enviou os dados,
     * retorna pendente=true. Após o envio, retorna o texto cifrado
     * uma única vez (a sessão é removida em seguida).
     *
     * @Route("/{id}", methods={"GET"}, requirements={"id"="[a-f0-9]{32}"})
     */
    public function consultar(string $id, CacheItemPoolInterface $cache): JsonResponse
    {
        $key = self::CACHE_PREFIX . $id;
        $item = $cache->getItem($key);

        if (!$item->isHit()) {
            return $this->json(['erro' => 'Sessão inválida ou expirada'], 404);
        }

        $dados = $item->get();

        if ($dados['cifrado'] === null) {
            return $this->json(['pendente' => true]);
        }

        // uso único: remove a sessão assim que a TV consumir o conteúdo
        $cache->deleteItem($key);

        return $this->json([
            'pendente' => false,
            'cifrado' => $dados['cifrado'],
        ]);
    }

    /**
     * Chamado pelo celular após preencher o formulário. Envia o
     * conteúdo já cifrado (a chave nunca passa pelo servidor).
     *
     * @Route("/{id}", methods={"PUT"}, requirements={"id"="[a-f0-9]{32}"})
     */
    public function enviar(string $id, Request $request, CacheItemPoolInterface $cache): JsonResponse
    {
        $key = self::CACHE_PREFIX . $id;
        $item = $cache->getItem($key);

        if (!$item->isHit()) {
            return $this->json(['erro' => 'Sessão inválida ou expirada'], 404);
        }

        $dados = $item->get();

        if ($dados['cifrado'] !== null) {
            return $this->json(['erro' => 'Sessão já utilizada'], 409);
        }

        $body = json_decode($request->getContent(), true);
        $cifrado = $body['cifrado'] ?? null;

        if (!$cifrado || !is_string($cifrado)) {
            return $this->json(['erro' => 'Conteúdo cifrado ausente'], 400);
        }

        $item->set(['cifrado' => $cifrado]);
        $item->expiresAfter(self::TTL);
        $cache->save($item);

        return $this->json(['ok' => true]);
    }
}
