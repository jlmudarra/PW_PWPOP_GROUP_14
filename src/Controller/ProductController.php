<?php


namespace PwPop\Controller;

use DateTime;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Container\ContainerInterface;
use PwPop\Model\Database\PDORepository;
use PwPop\Model\Product;
use PwPop\Model\User;


final class ProductController
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

    public function saveProductAction(Request $request, Response $response): Response{

        try{

            $data = $request->getParsedBody();

            /** @var PDORepository $repository */
            $repository = $this->container->get('user_repo');

            $user = $repository->takeUser($_SESSION['email']);
            $products=$repository->takeProducts();
            $id = sizeof($products) + 1;

            $name = (new FileController)->uploadProductAction($request,$response,$user->getUsername(),$id);
            //Cas en el que estem actualitzant el producte i no creantlo
            if($name==null){
                $name=$_SESSION['productInfo'];
            }

            $product = new Product(
                $id,
                $user->getUsername(),
                $data['title'],
                $data['description'],
                $data['price'],
                $data['category'],
                $name
            );


            if (!($name == 'error')) {

                //Si es correcta guardamos al usario en la database
                $repository->saveProduct($product);
                $_SESSION['success_message'] = 'Product Uploaded!';

                return $this->container->get('view')->render($response, 'index.twig', [
                    'products' => $_SESSION['products'] ?? null,
                    'confirmed' => $_SESSION['confirmed'] ?? null,
                    'success_message' => $_SESSION['success_message'] ?? null,
                    'email' => $_SESSION['email'] ?? null,
                    'logged' => $_SESSION['logged'] ?? false,
                    'profileImage' => $_SESSION['profileImage'] ?? null
                ]);

            } else {

                //DISPLAY ERROR IMG
                $errorImg = "Wrong Image Format, Accepted: jpg, png, jpeg";

                return $this->container->get('view')->render($response, 'upload.twig', [
                    'errorImg' => $errorImg ?? null,
                    'confirmed' => $_SESSION['confirmed'] ?? null,
                    'profileImage' => $_SESSION['profileImage'] ?? null,
                    'logged' => $_SESSION['logged'] ?? false,
                ]);

            }

        } catch (\Exception $e) {
            $response->getBody()->write('Unexpected error: ' . $e->getMessage());
            return $response->withStatus(500);
        }

    }


    public function loadProductInfo(Request $request, Response $response): Response{

        if($_SESSION['image'] == null){

            $_SESSION['image'] = $_REQUEST['image'];
            $_SESSION['productInfo'] = $_SESSION['image'];

            //Si esta accediendo de manera manual al producto
            if($_SESSION['productInfo'] == null){

                header('Location: /404');
                exit;

            }else{

                header('Location: /product');
                exit;

            }

        }else{

            /** @var PDORepository $repository */
            $repository = $this->container->get('user_repo');

            $products=$repository->takeProducts();

            foreach($products as $product){
                if($product[5] == $_SESSION['image']){
                    $my_product = $product;
                }
            }

            //Find if it's owner or buyer
            $user = $repository->takeUser($_SESSION['email']);
            $owner = false;
            if($my_product[1] == $user->getUsername()){
                $owner = true;
            }

            $images = scandir("../public/assets/img/Uploads/".$my_product[5]);
            $new = array_slice($images, 2, sizeof($images), true);



            return $this->container->get('view')->render($response, 'product.twig', [
                'success_message' => $_SESSION['success_message'] ?? null,
                'email' => $_SESSION['email'] ?? null,
                'logged' => $_SESSION['logged'] ?? false,
                'title' => $my_product[2] ?? null,
                'description' => $my_product[3] ?? null,
                'price' => $my_product[4] ?? null,
                'productsImg' => $new ?? null,
                'folder' => $my_product[5] ?? null,
                'category' => $my_product[6] ?? null,
                'owner' => $owner ?? null,
                'confirmed' => $_SESSION['confirmed'] ?? null,
                'profileImage' => $_SESSION['profileImage'] ?? null
            ]);
        }

    }


    public function updateAction(Request $request, Response $response): Response{

        try{

            $data = $request->getParsedBody();

            /** @var PDORepository $repository */
            $repository = $this->container->get('user_repo');

            $user = $repository->takeUser($_SESSION['email']);
            $products=$repository->takeProducts();
            $id=0;
            $name=$_SESSION['productInfo'];
            for($i=0; $i<sizeof($products);$i++){
                if($products[$i][5] == $name){
                    $id=$i+1;
                }
            }

            $product = new Product(
                $id,
                $user->getUsername(),
                $data['title'],
                $data['description'],
                $data['price'],
                $data['category'],
                $name
            );

            //Si es correcta guardamos al usario en la database
            $repository->updateProduct($product, $id);
            $_SESSION['success_message'] = 'Product Updated!';

            return $this->container->get('view')->render($response, 'index.twig', [
                'products' => $_SESSION['products'] ?? null,
                'confirmed' => $_SESSION['confirmed'] ?? null,
                'success_message' => $_SESSION['success_message'] ?? null,
                'email' => $_SESSION['email'] ?? null,
                'logged' => $_SESSION['logged'] ?? false,
                'profileImage' => $_SESSION['profileImage'] ?? null
            ]);


        } catch (\Exception $e) {
            $response->getBody()->write('Unexpected error: ' . $e->getMessage());
            return $response->withStatus(500);
        }

    }


    public function buyProduct(Request $request, Response $response): Response{

        try{

            /** @var PDORepository $repository */
            $repository = $this->container->get('user_repo');

            //repository soldout product

            if($_REQUEST['image'] != null){
                //Si cliquem a buy desde el home
                $image = $_REQUEST['image'];
            }else{
                //Si venim desde el overview del producte
                $image = $_SESSION['productInfo'];
            }

            $products=$repository->takeProducts();

            foreach($products as $product){
                if($product[5] == $image){
                    $my_product = $product;
                }
            }
            $buyer=$repository->takeUser($_SESSION['email']);
            $seller = $repository->takeEmail($my_product[1]);

            $repository->soldOutProduct($my_product[5]);


            //Enviar correo con info (Coger email del vendedor, y enviar usuario y mobil del comprador)
            (new MailerController())->buyMail($buyer,$seller);

            $_SESSION['success_message'] = 'Product Buyed!';

            return $this->container->get('view')->render($response, 'index.twig', [
                'products' => $_SESSION['products'] ?? null,
                'confirmed' => $_SESSION['confirmed'] ?? null,
                'success_message' => $_SESSION['success_message'] ?? null,
                'email' => $_SESSION['email'] ?? null,
                'logged' => $_SESSION['logged'] ?? false,
                'profileImage' => $_SESSION['profileImage'] ?? null
            ]);


        } catch (\Exception $e) {
            $response->getBody()->write('Unexpected error: ' . $e->getMessage());
            return $response->withStatus(500);
        }

    }

    public function deleteProduct(Request $request, Response $response): Response{

        try{

            $data = $request->getParsedBody();

            /** @var PDORepository $repository */
            $repository = $this->container->get('user_repo');

            $repository->deleteProduct($_SESSION['image']);

            $_SESSION['success_message'] = 'Product Deleted!';

            return $this->container->get('view')->render($response, 'index.twig', [
                'products' => $_SESSION['products'] ?? null,
                'confirmed' => $_SESSION['confirmed'] ?? null,
                'success_message' => $_SESSION['success_message'] ?? null,
                'logged' => $_SESSION['logged'] ?? false,
                'profileImage' => $_SESSION['profileImage'] ?? null
            ]);


        } catch (\Exception $e) {
            $response->getBody()->write('Unexpected error: ' . $e->getMessage());
        return $response->withStatus(500);
        }

    }

}