<?php

/*
 * This file is part of the Novo SGA project.
 *
 * (c) Rogerio Lino <rogeriolino@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Controller;

use Novosga\Entity\ServicoUsuario;
use Novosga\Entity\Usuario;
use Novosga\Http\Envelope;
use Novosga\Service\ServicoService;
use Novosga\Service\UsuarioService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;

/**
 * SettingsExtraController
 *
 * Ação extra para o módulo novosga.settings (aba Atendimento): vincula de
 * uma vez todos os serviços da unidade a um atendente, em vez de um por um.
 * Fica fora do bundle de terceiros (novosga/settings-bundle) para não
 * precisar alterar código do vendor.
 *
 * @Route("/novosga.settings")
 */
class SettingsExtraController extends AbstractController
{
    /**
     * @Route("/vincular-todos-servicos/{id}", name="novosga_settings_vincular_todos_servicos", methods={"POST"})
     */
    public function vincularTodos(
        Usuario $usuario,
        ServicoService $servicoService,
        UsuarioService $usuarioService
    ) {
        $em = $this->getDoctrine()->getManager();
        $unidade = $this->getUser()->getLotacao()->getUnidade();

        $servicosUnidade = $servicoService->servicosUnidade($unidade, ['ativo' => true]);

        $jaVinculados = $em
            ->getRepository(ServicoUsuario::class)
            ->getAll($usuario, $unidade);

        $idsVinculados = array_map(function (ServicoUsuario $su) {
            return $su->getServico()->getId();
        }, $jaVinculados);

        foreach ($servicosUnidade as $servicoUnidade) {
            $servico = $servicoUnidade->getServico();

            if (!in_array($servico->getId(), $idsVinculados, true)) {
                $usuarioService->addServicoUsuario($usuario, $servico, $unidade);
            }
        }

        $envelope = new Envelope();
        $envelope->setData(true);

        return $this->json($envelope);
    }
}
