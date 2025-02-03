<?php
namespace App\Controller;

use App\Entity\Website;
use App\Repository\WebsiteRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use App\Entity\User;
#[Route('/api/websites')]
class WebsiteController extends AbstractController
{
    private $entityManager;
    private $websiteRepository;

    public function __construct(EntityManagerInterface $entityManager, WebsiteRepository $websiteRepository)
    {
        $this->entityManager = $entityManager;
        $this->websiteRepository = $websiteRepository;
    }

    #[Route('/', name: 'api_website_index', methods: ['GET'])]
public function index(): JsonResponse
{
    $websites = $this->websiteRepository->findAll();

    $data = [];

    foreach ($websites as $website) {
        $data[] = [
            'id' => $website->getId(),
            'url' => $website->getUrl(),
            'availability' => $website->getAvailabilty(),
            'ssl_status' => $website->getSSLStatus(),
            'expires_in' => $website->getExpiresIn(),
            'last_check' => $website->getLastCheck()->format('Y-m-d H:i:s'),  
            'user' => $website->getUser()->getId(),
        ];
    }

    return new JsonResponse($data, Response::HTTP_OK);
}


    #[Route('/{id}', name: 'api_website_show', methods: ['GET'])]
    public function show(int $id): JsonResponse
    {
        $website = $this->websiteRepository->find($id);

        if (!$website) {
            return new JsonResponse(['message' => 'Website not found'], Response::HTTP_NOT_FOUND);
        }

        $data = [
            'id' => $website->getId(),
            'url' => $website->getUrl(),
            'availability' => $website->getAvailabilty(),
            'ssl_status' => $website->getSSLStatus(),
            'expires_in' => $website->getExpiresIn(),
            'user' => $website->getUser()->getId(),
        ];

        return new JsonResponse($data, Response::HTTP_OK);
    }

    #[Route('/new', name: 'api_website_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
    
       
        if (!$data || !isset($data['user_id'], $data['url'], $data['availability'], $data['ssl_status'], $data['expires_in'])) {
            return new JsonResponse(['message' => 'Invalid data'], Response::HTTP_BAD_REQUEST);
        }
    
        
        $user = $this->entityManager->getRepository(User::class)->find($data['user_id']);
    
       
        if (!$user) {
            return new JsonResponse(['message' => 'User not found'], Response::HTTP_NOT_FOUND);
        }
    
        
        $existingWebsite = $this->entityManager->getRepository(Website::class)
            ->findOneBy(['user' => $user, 'Url' => $data['url']]); 
    
        if ($existingWebsite) {
            
            return new JsonResponse(['message' => 'Website already exists for this user'], Response::HTTP_CONFLICT);
        }
    
       
        $website = new Website();
        $website->setUrl($data['url']);
        $website->setAvailabilty($data['availability']);
        $website->setSSLStatus($data['ssl_status']);
        $website->setExpiresIn($data['expires_in']);
        $website->setUser($user);
        $website->setLastCheck(new \DateTime()); 
    
       
        $this->entityManager->persist($website);
        $this->entityManager->flush();
    
      
        return new JsonResponse(['message' => 'Website created successfully'], Response::HTTP_CREATED);
    }
    
    

    #[Route('/{id}', name: 'api_website_update', methods: ['PUT'])]
public function update(int $id, Request $request): JsonResponse
{
    $website = $this->websiteRepository->find($id);

    if (!$website) {
        return new JsonResponse(['message' => 'Website not found'], Response::HTTP_NOT_FOUND);
    }

    $data = json_decode($request->getContent(), true);

    $website->setUrl($data['url']);
    $website->setAvailabilty($data['availability']);
    $website->setSSLStatus($data['ssl_status']);
    $website->setExpiresIn($data['expires_in']);
    $website->setLastCheck(new \DateTime());  

    $this->entityManager->flush();

    return new JsonResponse(['message' => 'Website updated successfully'], Response::HTTP_OK);
}


    #[Route('/{id}', name: 'api_website_delete', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $website = $this->websiteRepository->find($id);

        if (!$website) {
            return new JsonResponse(['message' => 'Website not found'], Response::HTTP_NOT_FOUND);
        }

        $this->entityManager->remove($website);
        $this->entityManager->flush();

        return new JsonResponse(['message' => 'Website deleted successfully'], Response::HTTP_NO_CONTENT);
    }


    #[Route('/user/{userId}', name: 'api_websites_by_user', methods: ['GET'])]
    public function getWebsitesByUser(int $userId): JsonResponse
    {
        $websites = $this->websiteRepository->findByUserId($userId);
    
        if (empty($websites)) {
            return new JsonResponse(['message' => 'No websites found for this user'], Response::HTTP_NOT_FOUND);
        }
    
        $data = [];
    
        foreach ($websites as $website) {
            $data[] = [
                'id' => $website->getId(),
                'url' => $website->getUrl(),
                'availability' => $website->getAvailabilty(),
                'ssl_status' => $website->getSSLStatus(),
                'expires_in' => $website->getExpiresIn(),
                'last_check' => $website->getLastCheck()->format('Y-m-d H:i:s'), 
                'user' => $website->getUser()->getId(),
            ];
        }
    
        return new JsonResponse($data, Response::HTTP_OK);
    }

}
