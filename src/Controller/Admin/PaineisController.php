<?php

/*
 * This file is part of the Novo SGA project.
 *
 * (c) Rogerio Lino <rogeriolino@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Controller\Admin;

use App\Entity\OAuthClient;
use App\Entity\OAuthRefreshToken;
use App\Entity\PainelDisplay;
use Exception;
use FOS\OAuthServerBundle\Model\ClientManagerInterface;
use Novosga\Entity\Unidade;
use Novosga\Http\Envelope;
use Novosga\Service\ServicoService;
use OAuth2\OAuth2;
use OAuth2\OAuth2ServerException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

/**
 * PaineisController
 *
 * Tela de administração para gerar painéis (TVs). Cada painel gerado aqui
 * recebe um identificador que, ao ser acessado em "painel/{id}" no app do
 * painel, se auto-configura sozinho (sem digitar servidor/usuário/senha).
 *
 * @Route("/admin/paineis")
 */
class PaineisController extends AbstractController
{
    private const SYSTEM_CLIENT_DESCRIPTION = 'painel-display-system';

    /**
     * @Route("/", name="admin_paineis_index")
     */
    public function index()
    {
        $unidades = $this
            ->getDoctrine()
            ->getManager()
            ->getRepository(Unidade::class)
            ->findBy([], ['nome' => 'ASC']);

        return $this->render('admin/paineis/index.html.twig', [
            'tab' => 'paineis',
            'unidades' => $unidades,
        ]);
    }

    /**
     * @Route("/api", name="admin_paineis_list", methods={"GET"})
     */
    public function list()
    {
        $envelope = new Envelope();

        $paineis = $this
            ->getDoctrine()
            ->getManager()
            ->getRepository(PainelDisplay::class)
            ->findBy([], ['criadoEm' => 'DESC']);

        $envelope->setData($paineis);

        return $this->json($envelope);
    }

    /**
     * @Route("/api/unidades/{id}/servicos", name="admin_paineis_servicos", methods={"GET"})
     */
    public function servicos(Unidade $unidade, ServicoService $service)
    {
        $envelope = new Envelope();
        $envelope->setData($service->servicosUnidade($unidade, ['ativo' => true]));

        return $this->json($envelope);
    }

    /**
     * @Route("/api", name="admin_paineis_new", methods={"POST"})
     */
    public function create(
        Request $request,
        ClientManagerInterface $clientManager,
        OAuth2 $oauth2Server
    ) {
        $envelope = new Envelope();
        $json = json_decode($request->getContent());

        $nome = isset($json->nome) ? trim($json->nome) : '';
        $unidadeId = $json->unidade ?? null;
        $servicos = $json->servicos ?? [];
        $username = $json->username ?? null;
        $password = $json->password ?? null;

        if ($nome === '') {
            throw new Exception('Informe um nome para o painel.');
        }

        if (!$unidadeId) {
            throw new Exception('Selecione a unidade.');
        }

        if (empty($servicos)) {
            throw new Exception('Selecione ao menos um serviço.');
        }

        if (!$username || !$password) {
            throw new Exception('Informe o usuário e a senha para autenticar o painel.');
        }

        $em = $this->getDoctrine()->getManager();
        $unidade = $em->getRepository(Unidade::class)->find($unidadeId);

        if (!$unidade) {
            throw new Exception('Unidade inválida.');
        }

        $client = $this->getSystemClient($clientManager);

        $tokenRequest = Request::create('/', 'POST', [
            'grant_type' => 'password',
            'client_id' => $client->getPublicId(),
            'client_secret' => $client->getSecret(),
            'username' => $username,
            'password' => $password,
        ]);

        try {
            $response = $oauth2Server->grantAccessToken($tokenRequest);
        } catch (OAuth2ServerException $e) {
            throw new Exception('Usuário ou senha inválidos.');
        }

        $data = json_decode($response->getContent(), true);

        $painel = new PainelDisplay();
        $painel->setId($this->generateId($em));
        $painel->setNome($nome);
        $painel->setUnidade($unidade);
        $painel->setServicos(array_values(array_map('intval', $servicos)));
        $painel->setRefreshToken($data['refresh_token']);
        $painel->setCriadoEm(new \DateTime());

        $em->persist($painel);
        $em->flush();

        $envelope->setData($painel);

        return $this->json($envelope);
    }

    /**
     * @Route("/api/{id}", name="admin_paineis_edit", methods={"PUT"}, requirements={"id"="[0-9]{1,10}"})
     */
    public function edit(string $id, Request $request)
    {
        $envelope = new Envelope();
        $em = $this->getDoctrine()->getManager();
        $painel = $em->getRepository(PainelDisplay::class)->find($id);

        if (!$painel) {
            throw new Exception('Painel não encontrado.');
        }

        $json = json_decode($request->getContent());

        $nome = isset($json->nome) ? trim($json->nome) : '';
        $unidadeId = $json->unidade ?? null;
        $servicos = $json->servicos ?? [];

        if ($nome === '') {
            throw new Exception('Informe um nome para o painel.');
        }

        if (!$unidadeId) {
            throw new Exception('Selecione a unidade.');
        }

        if (empty($servicos)) {
            throw new Exception('Selecione ao menos um serviço.');
        }

        $unidade = $em->getRepository(Unidade::class)->find($unidadeId);

        if (!$unidade) {
            throw new Exception('Unidade inválida.');
        }

        $painel->setNome($nome);
        $painel->setUnidade($unidade);
        $painel->setServicos(array_values(array_map('intval', $servicos)));

        $em->flush();

        $envelope->setData($painel);

        return $this->json($envelope);
    }

    /**
     * @Route("/api/{id}", name="admin_paineis_remove", methods={"DELETE"}, requirements={"id"="[0-9]{1,10}"})
     */
    public function remove(string $id)
    {
        $envelope = new Envelope();
        $em = $this->getDoctrine()->getManager();
        $painel = $em->getRepository(PainelDisplay::class)->find($id);

        if (!$painel) {
            throw new Exception('Painel não encontrado.');
        }

        $em->createQueryBuilder()
            ->delete(OAuthRefreshToken::class, 'e')
            ->where('e.token = :token')
            ->setParameter('token', $painel->getRefreshToken())
            ->getQuery()
            ->execute();

        $em->remove($painel);
        $em->flush();

        $envelope->setData($painel);

        return $this->json($envelope);
    }

    private function generateId(\Doctrine\ORM\EntityManagerInterface $em): string
    {
        $repo = $em->getRepository(PainelDisplay::class);

        do {
            $id = (string) random_int(1, 9999999999);
        } while ($repo->find($id) !== null);

        return $id;
    }

    private function getSystemClient(ClientManagerInterface $clientManager): OAuthClient
    {
        $repo = $this->getDoctrine()->getManager()->getRepository(OAuthClient::class);
        $client = $repo->findOneBy(['description' => self::SYSTEM_CLIENT_DESCRIPTION]);

        if ($client === null) {
            $client = $clientManager->createClient();
            $client->setDescription(self::SYSTEM_CLIENT_DESCRIPTION);
            $client->setAllowedGrantTypes(['password', 'refresh_token']);
            $clientManager->updateClient($client);
        }

        return $client;
    }
}
