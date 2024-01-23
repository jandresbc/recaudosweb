<?php

namespace AppBundle\Repository;

use Doctrine\ORM\EntityRepository as RepositoryClass;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
* Repositorio para tratar los permisos que tiene
* Cada tipo de usuario
**/
class PermisosRepository extends RepositoryClass
{
  //Funcion para validar si existe un permiso asignado
  //para una página y el rol que ha iniciado session.
  public function validarPermiso($pagina)
  {
    $em = $this->getEntityManager();
    $session = new Session();
    $rol = $session->get("rol");
    
    $pagina = $em->getRepository('AppBundle:Paginas')
    ->findBy(
      array(
        'pagina'=>trim($pagina)
      )
    );

    if(count($pagina) > 0){
      if($rol != "Superusuario" && $rol != "Inactivo"){
        $permisoRol = $em->getRepository('AppBundle:Permisos')
        ->findBy(
          array(
            'idGrupoUsuario'=>$session->get('idTipoRol'),
            'idPagina'=>$pagina[0]->getIdPagina()
          )
        );

        if(count($permisoRol) > 0){
          return true;
        }else if(count($permisoRol) == 0){
          return false;
        }
      }else if($rol == "Superusuario"){
        return true;
      }
    }else{
      throw new NotFoundHttpException("La página ha autorizar su permiso no se encuentra registrada en la Base de Datos.");
    }

  }
}
