<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;


use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;

use Doctrine\ORM\EntityManagerInterface;
use App\Entity\User;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[Route('/api', name: 'app_auth')]
class AuthController extends AbstractController
{

    public function __construct(EntityManagerInterface $entityManager)
    // constructor for initializing components required for auth managing
    {
        $this->entityManager = $entityManager;
    }

    #[Route('/signUp', name: 'signUp', methods: ["POST"])]
    public function signUp(Request $request, UserPasswordHasherInterface $hasher)
    {
        $user = new User();

        $data = $request->toArray();

        $user->setUsername($data['username']);
        $user->setRoles($data['roles']);
        $user->setPassword($hasher->hashPassword($user, $data['password']));

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        

        return new JsonResponse($serializer->serialize($user, 'json'), Response::HTTP_CREATED);
    }
}
