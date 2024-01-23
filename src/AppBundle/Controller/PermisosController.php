<?php

namespace AppBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Request;

use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Doctrine\ORM\EntityRepository;

//Auditoria
use AppBundle\Blockchain\Block;
use AppBundle\Blockchain\Blockchain;

class PermisosController extends Controller
{
    public function indexAction(Request $request)
    {
      $session = new Session();
      if($session->get('auth') == 1){
        $em = $this->getDoctrine()->getManager();

        $permiso = $em->getRepository('AppBundle:Permisos')
        ->validarPermiso('permisos');

        if($permiso === true){
          $mainService = $this->get('main');
          //Auditoria.
          $blockchain = new Blockchain($em);
          $form = $this->createFormBuilder()
          ->add("empresa",EntityType::class,[
            "label"=>"Empresa",
            "attr"=>["class"=>"form-control select2"],
            "class"=>"AppBundle:Empresas",
            "query_builder"=>function(EntityRepository $er){
              $session = new Session();
              $query = null;
              $em = $GLOBALS['kernel']->getContainer()
              ->get('doctrine')->getEntityManager();

              if($session->get('rol') != 'Superusuario'){
                $ESA = $em->createQuery("
                  SELECT ESA FROM
                  AppBundle:EmpresasSedesAgencias ESA
                  WHERE ESA.idSedeAgencia = :idSAgencia
                ")->setParameter("idSAgencia",$session->get('idSedeAgencia'))
                ->getResult();
              }

              $query = $er->createQueryBuilder("E");

              if($session->get('rol') != 'Superusuario'){
                foreach ($ESA as $key => $value) {
                  $query->andWhere("E.idEmpresa = :idEmp".$key)
                  ->setParameter("idEmp".$key,$value->getIdEmpresa()->getIdEmpresa());
                }
              }

              return $query;
            }
          ])->add('idGrupoUsuario',EntityType::class,[
            "label"=>"Perfil: *",
            "attr"=>["class"=>"form-control select2"],
            "placeholder"=>"Seleccione",
            "class"=>"AppBundle:GruposUsuarios",
            "query_builder"=>function(EntityRepository $er){
                $session = new Session();
                $em = $GLOBALS['kernel']->getContainer()->get('doctrine')->getEntityManager();
                $rol = $session->get("rol");

                $query = $er->createQueryBuilder("GU");

                if($rol == 'Administrador Agencias'){
                  $query->andWhere("GU.grupoUsuario <> 'Superusuario'")
                  ->andWhere("GU.grupoUsuario <> 'Administrador'")
                  ->andWhere("GU.grupoUsuario <> 'Auditor'");
                }else if($rol == 'Administrador'){
                  $query->andWhere("GU.grupoUsuario <> 'Superusuario'");
                  $query->andWhere("GU.grupoUsuario <> 'Inactivo'");
                }else if($rol == 'Auditor'){
                  $query->andWhere("GU.grupoUsuario = 'Auditor'");
                }else if($rol == 'Cajero'){
                  $query->andWhere("GU.grupoUsuario = 'Cajero'");
                }

                return $query;
            }
          ])->add("sinAcceso",ChoiceType::class,[
              "label"=>"Acceso Denegado",
              "multiple"=>true,
              "expanded"=>false,
              "attr"=>["class"=>"form-control","style"=>"height:350px"]
          ])->add("conAcceso",ChoiceType::class,[
              "label"=>"Acceso Permitido",
              "multiple"=>true,
              "expanded"=>false,
              "attr"=>["class"=>"form-control","style"=>"height:350px"]
          ])->getForm();
          $form->handleRequest($request);

          if($form->isSubmitted() && $form->isValid()){
            $em->getConnection()->beginTransaction();

            try{
              $now = new \DateTime('now', new \DateTimeZone("America/Bogota"));
              $Cajas->setFechaHoraCreacion($now);

              $em->persist($Cajas);
              $em->flush($Cajas);

              //Auditoria agrega un bloque a la cadena.
              $dataAud = [
                "accion"=>"Insert",
                "tabla"=>"Cajas",
                "id_datos"=>$Cajas->getIdCaja(),
                "data"=>$Cajas->getArrayData()
              ];

              $blockchain->addBlock(new Block($dataAud));

              $em->getConnection()->commit();

              //Registra los bloques de la cadena en la bd.
              $blockchain->registerChain();

              //Redirecciona a recaudos.
              return $this->redirectToRoute('permisos');
            }catch(Exception $e){
                $blockchain->chain = [];//Reinicializa la cadena en caso de un error.
                $em->getConnection()->rollback();
                throw $e;
            }
          }

          //Auditoria agrega un bloque a la cadena.
          $dataAud = [
            "accion"=>"Load",
            "tabla"=>"Entro al módulo de Asignar Permisos",
            "id_datos"=>"vista asignar permisos",
            "data"=>["cargó vista"]
          ];

          $blockchain->addBlock(new Block($dataAud));
          $blockchain->registerChain();

          return $this->render('@AppBundle/Permisos/Permisos.html.twig', array(
              "form" => $form->createView()
          ));
        }else if($permiso === false){
         return $this->redirectToRoute("error",
           array('codigo'=>'101')
         );
      }
     }else{
       return $this->redirectToRoute("error",
         array('codigo'=>'100')
       );
     }
    }

}
