<?php

namespace AppBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;


class errorController extends Controller
{

  private $codigosErrores = array(
    "100" => "El sistema detecto que está intentando acceder a un sitio restringido. Intente iniciar sesión para acceder a este sitio.",
    "101" => "El sistema detecto que no cuenta con los privilegios necesarios para acceder a este sitio. Para obtener acceso a este sitio contacte al administrador del sistema."
  );

  public function indexAction(Request $request,$codigo)
  {
    $em = $this->getDoctrine()->getManager();
    $session = $request->getSession();

    return $this->render("@AppBundle/Error/Error.html.twig",
      array(
        "mensaje_error" => $this->codigosErrores[$codigo],
        "empresa" => $session->get('empresa'),
      )
    );
  }

}
