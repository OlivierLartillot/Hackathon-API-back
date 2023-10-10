<?php

namespace App\Controller;

use App\Entity\Book;
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
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;
use Nelmio\ApiDocBundle\Annotation\Model;
use Nelmio\ApiDocBundle\Annotation\Security;
use OpenApi\Annotations as OA;

#[Route('/api')]
class BookController extends AbstractController
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
     * Recover book list.
     *
     * @OA\Response(
     *     response=200,
     *     description="Recover the list of books.",
     *     @OA\JsonContent(
     *        type="array",
     *        @OA\Items(ref=@Model(type=Book::class, groups={"getBooks"}))
     *     )
     * )
     * 
     * @OA\Tag(name="Books")
     * 
     */
    #[Route('/books', name: 'getBooks', methods: ['GET'])]
    public function getBooks(BookRepository $bookRepository, SerializerInterface $serializer): JsonResponse
    {
        $bookList = $bookRepository->findAll();
        $jsonBookList = $serializer->serialize($bookList, 'json', ['groups' => 'getBooks']);

        return new JsonResponse($jsonBookList, Response::HTTP_OK, [], true);
    }

    /**
     * Recover book list PAGINATED.
     *
     * @OA\Response(
     *     response=200,
     *     description="Recover the list of books PAGINATED.",
     *     @OA\JsonContent(
     *        type="array",
     *        @OA\Items(ref=@Model(type=Book::class, groups={"getBooks"}))
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
     * @OA\Tag(name="Books")
     * 
     */
    #[Route('/bookspages', name: 'getBooksPaginated', methods: ['GET'])]
    public function getBooksPaginated(Request $request, BookRepository $bookRepository, TagAwareCacheInterface $cache, SerializerInterface $serializer): JsonResponse
    {

        $page = $request->get('page', 1);
        $limit = $request->get('limit', 3);


        $bookList = $bookRepository->findAllWithPagination($page, $limit);
        $jsonBookList = $serializer->serialize($bookList, 'json', ['groups' => 'getBooks']);

        // ------------------------- CACHE SET UP -------------------------
        // $idCache = 'getBooks-' . $page . "-" . $limit;

        // $jsonBookList = $cache->get($idCache, function (ItemInterface $item) use ($bookRepository, $page, $limit, $serializer) {
        //     // echo ('Cache has been set!');
        //     $item->tag('booksCache');
        //     $item->expiresAfter(60);
        //     $bookList = $bookRepository->findAllWithPagination($page, $limit);
        //     return $serializer->serialize($bookList, 'json', ['groups' => 'getBooks']);
        // });

        return new JsonResponse($jsonBookList, Response::HTTP_OK, [], true);
    }

    /**
     * Recover individual book.
     *
     * @OA\Response(
     *     response=200,
     *     description="Recover individual book.",
     *     @OA\JsonContent(
     *        type="array",
     *        @OA\Items(ref=@Model(type=Book::class, groups={"getBooks"}))
     *     )
     * )
     * 
     * @OA\Tag(name="Books")
     * 
     */
    #[Route('/books/{id}', name: 'getBook', methods: ['GET'])]
    public function getBook(Book $book, SerializerInterface $serializer): JsonResponse
    {
        $jsonBook = $serializer->serialize($book, 'json', ['groups' => 'getBooks']);
        return new JsonResponse($jsonBook, Response::HTTP_OK, [], true);
    }

    /**
     * Delete individual book. ADMIN ONLY
     *
     * @OA\Response(
     *     response=200,
     *     description="Delete individual book.",
     *     @OA\JsonContent(
     *        type="array",
     *        @OA\Items(ref=@Model(type=Book::class, groups={"getBooks"}))
     *     )
     * )
     * 
     * @OA\Tag(name="Books")
     * 
     */
    #[Route('/books/{id}', name: 'deleteBook', methods: ['DELETE'])]
    #[IsGranted('ROLE_ADMIN', message: 'You don\'t have access.')]
    public function deleteBook(Book $book, EntityManagerInterface $em, TagAwareCacheInterface $cache): JsonResponse
    {
        // $cache->invalidateTags(['booksCache']);
        $em->remove($book);
        $em->flush();

        return new JsonResponse("Resource deleted.", Response::HTTP_NO_CONTENT);
    }

    /**
     * Create individual book. ADMIN ONLY
     *
     * @OA\Response(
     *     response=200,
     *     description="Create individual book.",
     *     @OA\JsonContent(
     *        type="array",
     *        @OA\Items(ref=@Model(type=Book::class, groups={"getBooks"}))
     *     )
     * )
     * 
     * @OA\Tag(name="Books")
     * 
     */
    #[Route('/books', name: 'createBook', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN', message: 'You don\'t have access.')]
    public function createBook(Request $request, SerializerInterface $serializer, EntityManagerInterface $em, UrlGeneratorInterface $urlGenerator, AuthorRepository $authorRepository, ValidatorInterface $validator): JsonResponse
    {

        $book = $serializer->deserialize($request->getContent(), Book::class, 'json');

        // Error verification
        $errors = $validator->validate($book);

        if ($errors->count() > 0) {
            return new JsonResponse($serializer->serialize($errors, 'json'), Response::HTTP_BAD_REQUEST, [], true);
        }

        $content = $request->toArray();
        $idAuthor = $content['idAuthor'] ?? -1;

        $book->setAuthor($authorRepository->find($idAuthor));

        $em->persist($book);
        $em->flush();

        $jsonBook = $serializer->serialize($book, 'json', ['groups' => 'getBooks']);
        $location = $urlGenerator->generate('getBook', ['id' => $book->getId()], UrlGeneratorInterface::ABSOLUTE_URL);

        return new JsonResponse($jsonBook, Response::HTTP_CREATED, ["location" => $location], true);
    }

    /**
     * Update individual book.
     *
     * @OA\Response(
     *     response=200,
     *     description="Update individual book.",
     *     @OA\JsonContent(
     *        type="array",
     *        @OA\Items(ref=@Model(type=Book::class, groups={"getBooks"}))
     *     )
     * )
     * 
     * @OA\Tag(name="Books")
     * 
     */
    #[Route('/books/{id}', name: 'updateBook', methods: ['PUT'])]
    public function updateBook(Book $currentBook, Request $request, SerializerInterface $serializer, EntityManagerInterface $em, AuthorRepository $authorRepository): JsonResponse
    {
        $updatedBook = $serializer->deserialize($request->getContent(), Book::class, 'json', [AbstractNormalizer::OBJECT_TO_POPULATE => $currentBook]);

        $content = $request->toArray();
        $idAuthor = $content['idAuthor'] ?? -1;

        $updatedBook->setAuthor($authorRepository->find($idAuthor));

        $em->persist($updatedBook);
        $em->flush();

        $jsonUpdatedBook = $serializer->serialize($updatedBook, 'json', ['groups' => 'getBooks']);
        return new JsonResponse($jsonUpdatedBook, Response::HTTP_OK, [], true);
    }
}