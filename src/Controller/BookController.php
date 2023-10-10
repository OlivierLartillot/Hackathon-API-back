<?php

namespace App\Controller;

use App\Entity\Author;
use App\Repository\BookRepository;
use App\Repository\AuthorRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\SerializerInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Nelmio\ApiDocBundle\Annotation\Model;
use Nelmio\ApiDocBundle\Annotation\Security;
use OpenApi\Annotations as OA;

#[Route('/api')]
class AuthorController extends AbstractController
{

    /**
     * @OA\Schema(
     *     schema="Author",
     *     type="object",
     *     @OA\Property(property="id", type="integer"),
     *     @OA\Property(property="firstname", type="string"),
     *     @OA\Property(property="lastname", type="string"),
     *     @OA\Property(property="books", type="array", @OA\Items(ref="#/components/schemas/Book"))
     * )
     */

    /**
     * @OA\Schema(
     *     schema="Book",
     *     type="object",
     *     @OA\Property(property="id", type="integer"),
     *     @OA\Property(property="title", type="string"),
     *     @OA\Property(property="description", type="string"),
     *     @OA\Property(property="author", ref="#/components/schemas/Author")
     * )
     */

    /**
     * Recover author list PAGINATED.
     *
     * @OA\Response(
     *     response=200,
     *     description="Recover the list of authors PAGINATED.",
     *     @OA\JsonContent(
     *        type="array",
     *        @OA\Items(ref=@Model(type=Author::class, groups={"getAuthors"}))
     *     )
     * )
     * 
     * @OA\Parameter(
     *     name="page",
     *     in="query",
     *     description="Page number",
     *     @OA\Schema(type="int")
     * )
     *
     * @OA\Parameter(
     *     name="limit",
     *     in="query",
     *     description="Number of results",
     *     @OA\Schema(type="int")
     * )
     * @OA\Tag(name="Authors")
     * 
     */
    #[Route('/authorspages', name: 'getAuthorsPaginated', methods: ['GET'])]
    public function getAuthorsPaginated(AuthorRepository $authorRepository, SerializerInterface $serializer, Request $request): JsonResponse
    {

        $page = $request->get('page', 1);
        $limit = $request->get('limit', 3);

        // $authorList = $authorRepository->findAll();
        $authorList = $authorRepository->findAllWithPagination($page, $limit);
        $jsonAuthorList = $serializer->serialize($authorList, 'json', ['groups' => 'getAuthors']);

        return new JsonResponse($jsonAuthorList, Response::HTTP_OK, [], true);
    }

    /**
     * Recover individual author.
     *
     * @OA\Response(
     *     response=200,
     *     description="Recover individual author.",
     *     @OA\JsonContent(
     *        type="array",
     *        @OA\Items(ref=@Model(type=Author::class, groups={"getAuthors"}))
     *     )
     * )
     * 
     * @OA\Tag(name="Authors")
     * 
     */
    #[Route('/authors/{id}', name: 'getAuthor', methods: ['GET'])]
    public function getAuthor(Author $author, SerializerInterface $serializer): JsonResponse
    {
        $jsonAuthor = $serializer->serialize($author, 'json', ['groups' => 'getAuthors']);
        return new JsonResponse($jsonAuthor, Response::HTTP_OK, [], true);
    }

    /**
     * Create individual author. ADMIN ONLY
     *
     * @OA\Response(
     *     response=200,
     *     description="Create individual author.",
     *     @OA\JsonContent(
     *        type="array",
     *        @OA\Items(ref=@Model(type=Author::class, groups={"getAuthors"}))
     *     )
     * )
     * 
     * @OA\Tag(name="Authors")
     * 
     */
    #[Route('/authors', name: 'createAuthor', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN', message: 'You don\'t have access.')]
    public function createAuthor(Request $request, SerializerInterface $serializer, EntityManagerInterface $em, UrlGeneratorInterface $urlGenerator, BookRepository $bookRepository): JsonResponse
    {

        // Deserialize the JSON body into an object
        $author = $serializer->deserialize($request->getContent(), Author::class, 'json');

        // Turn the request into an array, and extract the ids
        $content = $request->toArray();
        $idBooks = $content['idBooks'] ?? [];

        // Loop on each id and add it to the author's books if it exists
        foreach ($idBooks as $id) {
            $book = $bookRepository->find($id);
            if ($book) {
                $author->addBook($book);
            }
        }

        // Persist and flush to the DB
        $em->persist($author);
        $em->flush();

        // Return a JSON response to the console 
        $jsonAuthor = $serializer->serialize($author, 'json', ['groups' => 'getAuthors']);
        $location = $urlGenerator->generate('detailAuthor', ['id' => $author->getId()], UrlGeneratorInterface::ABSOLUTE_URL);

        return new JsonResponse($jsonAuthor, Response::HTTP_CREATED, ["location" => $location], true);
    }

    /**
     * Update individual author.
     *
     * @OA\Response(
     *     response=200,
     *     description="Update individual author.",
     *     @OA\JsonContent(
     *        type="array",
     *        @OA\Items(ref=@Model(type=Author::class, groups={"getAuthors"}))
     *     )
     * )
     * 
     * @OA\Tag(name="Authors")
     * 
     */

    #[Route('/authors/{id}', name: 'updateAuthor', methods: ['PUT'])]
    public function updateAuthor(BookRepository $bookRepository, Author $currentAuthor, Request $request, SerializerInterface $serializer, EntityManagerInterface $em, AuthorRepository $authorRepository): JsonResponse
    {
        $updatedAuthor = $serializer->deserialize($request->getContent(), Author::class, 'json', [AbstractNormalizer::OBJECT_TO_POPULATE => $currentAuthor]);

        // Turn the request into an array, and extract the ids
        $content = $request->toArray();
        $idBooks = $content['idBooks'] ?? [];

        // Clear book list
        $updatedAuthor->getBooks()->clear();

        // Loop on each id and add it to the author's books if it exists
        foreach ($idBooks as $id) {
            $book = $bookRepository->find($id);
            if ($book) {
                $currentAuthor->addBook($book);
            }
        }

        $em->persist($updatedAuthor);
        $em->flush();

        $jsonUpdatedAuthor = $serializer->serialize($updatedAuthor, 'json', ['groups' => 'getAuthors']);
        return new JsonResponse($jsonUpdatedAuthor, Response::HTTP_OK, [], true);
    }

    /**
     * Delete individual author. ADMIN ONLY
     *
     * @OA\Response(
     *     response=200,
     *     description="Delete individual author.",
     *     @OA\JsonContent(
     *        type="array",
     *        @OA\Items(ref=@Model(type=Author::class, groups={"getAuthors"}))
     *     )
     * )
     * 
     * @OA\Tag(name="Authors")
     * 
     */
    #[Route('/authors/{id}', name: 'deleteAuthor', methods: ['DELETE'])]
    #[IsGranted('ROLE_ADMIN', message: 'You don\'t have access.')]
    public function deleteAuthor(Author $author, EntityManagerInterface $em): JsonResponse
    {

        $em->remove($author);
        $em->flush();
        // dd($author->getBooks());

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }
}