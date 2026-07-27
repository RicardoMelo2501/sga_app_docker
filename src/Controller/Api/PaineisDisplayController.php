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

use App\Entity\OAuthClient;
use App\Entity\PainelDisplay;
use OAuth2\OAuth2;
use OAuth2\OAuth2ServerException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

/**
 * PaineisDisplayController
 *
 * Endpoint público (identificado apenas pelo id opaco do painel) usado pelo
 * app de painel para se auto-configurar. Nenhuma credencial é exposta aqui:
 * o backend troca o refresh token guardado por um access token novo a cada
 * chamada.
 *
 * @Route("/api/paineis")
 */
class PaineisDisplayController extends AbstractController
{
    private const SYSTEM_CLIENT_DESCRIPTION = 'painel-display-system';

    /**
     * @Route("/{id}/token", methods={"GET"}, requirements={"id"="[0-9]{1,10}"})
     */
    public function token(string $id, OAuth2 $oauth2Server): JsonResponse
    {
        $em = $this->getDoctrine()->getManager();
        $painel = $em->getRepository(PainelDisplay::class)->find($id);

        if (!$painel) {
            return $this->json(['erro' => 'Painel não encontrado'], 404);
        }

        $client = $em->getRepository(OAuthClient::class)->findOneBy([
            'description' => self::SYSTEM_CLIENT_DESCRIPTION,
        ]);

        if (!$client) {
            return $this->json(['erro' => 'Painel não configurado corretamente'], 500);
        }

        $tokenRequest = Request::create('/', 'POST', [
            'grant_type' => 'refresh_token',
            'client_id' => $client->getPublicId(),
            'client_secret' => $client->getSecret(),
            'refresh_token' => $painel->getRefreshToken(),
        ]);

        try {
            $response = $oauth2Server->grantAccessToken($tokenRequest);
        } catch (OAuth2ServerException $e) {
            return $this->json(['erro' => 'Painel expirado ou revogado'], 410);
        }

        $data = json_decode($response->getContent(), true);

        // o refresh token é rotacionado a cada uso, precisa ser atualizado
        $painel->setRefreshToken($data['refresh_token']);
        $em->flush();

        return $this->json([
            'accessToken' => $data['access_token'],
            'expiresIn' => $data['expires_in'],
            'unidade' => $painel->getUnidade()->getId(),
            'servicos' => $painel->getServicos(),
        ]);
    }
}
