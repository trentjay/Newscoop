<?php
/**
 * @package Newscoop\Gimme
 * @author Yorick Terweijden <yorick.terweijden@sourcefabric.org>
 * @copyright 2014 Sourcefabric o.p.s.
 * @license http://www.gnu.org/licenses/gpl-3.0.txt
 */

namespace Newscoop\GimmeBundle\Controller;

use FOS\RestBundle\Controller\FOSRestController;
use FOS\RestBundle\Controller\Annotations\View;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Nelmio\ApiDocBundle\Annotation\ApiDoc;
use Doctrine\ORM\EntityNotFoundException;
use Newscoop\GimmeBundle\Form\Type\SnippetType;
use Symfony\Component\Form\Exception\InvalidArgumentException;
use Newscoop\Entity\Snippet;
use Newscoop\Entity\Snippet\SnippetTemplate;
use Newscoop\Entity\Snippet\SnippetTemplate\SnippetTemplateField;

class SnippetTemplatesController extends FOSRestController
{
    /**
     * Get all SnippetTemplates
     *
     * @ApiDoc(
     *     statusCodes={
     *         200="Returned when successful",
     *         404={
     *           "Returned when the snippets are not found"
     *         }
     *     },
     *     parameters={
     *         {"name"="show", "dataType"="string", "required"=false, "description"="Define which snippets to show, 'enabled', 'disabled', 'all'. Defaults to 'enabled'"}
     *     },
     * )
     *
     * @Route("/snippetTemplates.{_format}/{show}", defaults={"_format"="json", "show"="enabled"})
     * @Method("GET")
     * @View(serializerGroups={"list"})
     *
     * @return array
     */
    public function getSnippetTemplatesAction($show)
    {
        $em = $this->container->get('em');

        $snippetTemplates = $em->getRepository('Newscoop\Entity\Snippet\SnippetTemplate')
            ->getSnippetTemplateQueryBuilder($show);

        $paginator = $this->get('newscoop.paginator.paginator_service');
        $snippetTemplates = $paginator->paginate($snippetTemplates, array(
            'distinct' => false
        ));

        return $snippetTemplates;
    }

    /**
     * Get SnippetTemplate
     *
     * @ApiDoc(
     *     statusCodes={
     *         200="Returned when successful",
     *         404={
     *           "Returned when the SnippetTemplate is not found",
     *         }
     *     },
     *     parameters={
     *         {"name"="id", "dataType"="integer", "required"=true, "description"="SnippetTemplate id"},
     *         {"name"="show", "dataType"="string", "required"=false, "description"="Define which SnippetTemplates to show, 'enabled', 'disabled', 'all'. Defaults to 'enabled'"}
     *     },
     *     output="\Newscoop\Entity\SnippetTemplate"
     * )
     *
     * @Route("/snippetTemplates/{id}.{_format}/{show}", defaults={"_format"="json", "show"="enabled"})
     * @Method("GET")
     * @View(serializerGroups={"details"})
     *
     * @return array
     */
    public function getSingleSnippetTemplateAction($id, $show)
    {
        $em = $this->container->get('em');

        $snippetTemplate = $em->getRepository('Newscoop\Entity\Snippet\SnippetTemplate')
            ->getTemplateById($id, $show);

        if (!$snippetTemplate) {
            throw new EntityNotFoundException('Result was not found.');
        }

        return $snippetTemplate;
    }

}
