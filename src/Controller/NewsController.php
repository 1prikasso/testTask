<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;

use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;


use Symfony\Component\Security\Core\User\UserInterface;
use Doctrine\ORM\EntityManagerInterface;
use App\Entity\News;
use App\Entity\User;

use App\Repository\NewsRepository;
 
class NewsController extends AbstractController
{
    public function __construct(EntityManagerInterface $entityManager)
    // constructor for initializing components required for auth managing
    {
        $encoders = [new JsonEncoder()];
        $normalizers = [new ObjectNormalizer()];

        $this->serializer = new Serializer($normalizers, $encoders);

        $this->entityManager = $entityManager;
    }
    
    // show news with filtering and pagination
    #[Route('/api/news', name:"news.show", methods:["GET"])]
    function show(Request $request, NewsRepository $newsRepository) : JsonResponse {
    // inputs: From get request: page number, count of elements
    //         author id, title of news
    // outputs: JSON of news with pagintaion settings
        
        $page = $request->get('page') ? intval($request->get('page')) : 1;
        $limit = $request->get('limit') ? intval($request->get('limit')): 10;
        $order = $request->get('order') ? 'ASC' : 'DESC';

        $news_id = $request->get('id') ? $request->get('id') : null;
        $author = $request->get('author') ? $request->get('author') : null;
        $title = $request->get('title') ? $request->get('title') : null;

        $crits = [];

        if ($news_id)
            $crits['id'] = $news_id;
        if ($author)
            $crits['author'] = $author;
        if ($title)
            $crits['title'] = $title;

        $news = $newsRepository->findBy(
            $crits,
            ['id' => $order], 
            $limit,
            ($page - 1) * $limit
        );

        $total = $newsRepository->count($crits);

        return new JsonResponse(
            $this->serializer->serialize(
                [
                    'data' => $news,
                    'pagination' => [
                        'total' => $total,
                        'page' => $page,
                        'limit' => $limit,
                        'total_pages' => ceil($total / $limit),
                    ],
                ], 
                'json',
                [
                    'circular_reference_handler' => function ($object) {
                        return $object->getId();
                    }
                ]
            ), 
        Response::HTTP_OK);
    }

    
    // store news
    #[Route('/api/news', name:"news.store", methods:["POST"])]
    public function store(Request $request, SluggerInterface $slugger) : JsonResponse{
        // inputs: get title text and image from POST-request
        // ouput: JSON of id of the news 

        $news = new News;
        
        $news->setTitle($request->get('title'));
        $news->setText($request->get('title'));
        
        $imageFile = $request->files->get('image');

        if ($imageFile) {
            $newFilename = pathinfo($imageFile->getClientOriginalName(), PATHINFO_FILENAME);
            $newFilename = $slugger->slug($newFilename);
            $newFilename = $newFilename.'-'.uniqid().'.'.pathinfo($imageFile, PATHINFO_EXTENSION);

            $news->setImage($newFilename);
        }

        $this->getUser()->addNo($news);

        $this->entityManager->persist($news);
        $this->entityManager->flush();


        return new JsonResponse(
            $this->serializer->serialize(
                $news, 
                'json',
                [
                    'circular_reference_handler' => function ($object) {
                        return $object->getId();
                    }
                ]
            ), 
            Response::HTTP_OK);
    }

    #[Route('/api/news/{id}', name:"news.delete", methods:["DELETE"])]
    public function delete(News $news) : JsonResponse {
        
        // return dump($news->getAuthor());

        if($news->getAuthor()->getId() == $this->getUser()->getId()){            
            $this->entityManager->remove($news);
            $this->entityManager->flush();
            return new JsonResponse(json_encode(["message"=>"removed correectly"]), Response::HTTP_OK);
        }
        
        return new JsonResponse(json_encode(["message"=>"Access denied"]), Response::HTTP_NOT_ACCEPTABLE); 
    }
    
}
