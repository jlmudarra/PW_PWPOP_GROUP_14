<?php

namespace PwPop\Controller;

use DateTime;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Container\ContainerInterface;
use PwPop\Model\Database\PDORepository;
use PwPop\Model\Product;
use PwPop\Model\User;

final class MyProductsController
{
    /** @var ContainerInterface */
    private $container;

    /**
     * HelloController constructor.
     * @param ContainerInterface $container
     */
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    public function productsUpdate(Request $request, Response $response): Response
    {
        try {

            $_SESSION['image'] = null;

            /** @var PDORepository $repository */
            $repository = $this->container->get('user_repo');

            $products = $repository->takeProducts();

            //Agafem el usuari per gestionar que no mostri els seus productes
            $user = $repository->takeUser($_SESSION['email']);
            $j = 0;
            $newArray=[];

            while(sizeof($products) > $j){
                if($products[$j][1] == $user->getUsername() && $products[$j][8] == 0 && $products[$j][7] == 1){
                    array_push($newArray, $products[$j]);
                }
                $j++;
            }

            if($newArray == null){
                $_SESSION['success_message'] = 'No products Found!';
            }else{
                $_SESSION['success_message'] = '';
            }

            $_SESSION['my_products'] = $newArray;

            return $this->container->get('view')->render($response, 'myproducts.twig', [
                'products' => $_SESSION['my_products'] ?? null,
                'confirmed' => $_SESSION['confirmed'] ?? null,
                'success_message' => $_SESSION['success_message'] ?? null,
                'logged' => $_SESSION['logged'] ?? false,
                'email' => $_SESSION['email'] ?? null,
                'profileImage' => $_SESSION['profileImage'] ?? null
            ]);

        } catch (\Exception $e) {
            $response->getBody()->write('Unexpected error: ' . $e->getMessage());
            return $response->withStatus(500);
        }
    }
}