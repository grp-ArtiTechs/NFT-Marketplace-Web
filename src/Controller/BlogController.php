<?php

namespace App\Controller;

use App\Entity\Blog;
use App\Entity\Comment;
use App\Form\BlogType;
use App\Form\CommentType;
use App\Repository\BlogRepository;
use App\Service\TranslationService;
use App\Service\ProfanityFilter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[Route('/blog')]
class BlogController extends AbstractController
{
    private TranslationService $translationService;
    private EntityManagerInterface $entityManager;
    private ProfanityFilter $profanityFilter;

    public function __construct(
        TranslationService $translationService,
        EntityManagerInterface $entityManager,
        ProfanityFilter $profanityFilter
    ) {
        $this->translationService = $translationService;
        $this->entityManager = $entityManager;
        $this->profanityFilter = $profanityFilter;
    }

    #[Route('/', name: 'app_blog_index', methods: ['GET'])]
    public function index(BlogRepository $blogRepository): Response
    {
        $blogs = $blogRepository->findAll();
        $commentForms = [];

        foreach ($blogs as $blog) {
            $comment = new Comment();
            $comment->setBlog($blog);
            $form = $this->createForm(CommentType::class, $comment, [
                'action' => $this->generateUrl('app_blog_add_comment', ['id' => $blog->getId()])
            ]);
            $commentForms[$blog->getId()] = $form->createView();
        }

        return $this->render('blog/index.html.twig', [
            'blogs' => $blogs,
            'commentForms' => $commentForms,
        ]);
    }

    #[Route('/{id}/comment', name: 'app_blog_add_comment', methods: ['POST'])]
    public function addComment(Request $request, Blog $blog, EntityManagerInterface $entityManager): Response
    {
        // Check if user is authenticated
        if (!$this->getUser()) {
            return $this->redirectToRoute('app_login');
        }
        
        $comment = new Comment();
        $comment->setBlog($blog);
        $comment->setUser($this->getUser());
        $comment->setCreatedAt(new \DateTimeImmutable());

        $form = $this->createForm(CommentType::class, $comment);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Ensure user is set again after form submission
            $comment->setUser($this->getUser());
            $entityManager->persist($comment);
            $entityManager->flush();

            $this->addFlash('success_blog', 'Your comment has been added successfully!');
        }

        return $this->redirectToRoute('app_blog_index');
    }

    #[Route('/new', name: 'app_blog_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager, SluggerInterface $slugger): Response
    {
        // Check if user is authenticated and redirect to login if not
        if (!$this->getUser()) {
            return $this->redirectToRoute('app_login');
        }
        
        $blog = new Blog();
        $blog->setUser($this->getUser());
        $blog->setDate(new \DateTime());
        
        $form = $this->createForm(BlogType::class, $blog);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Check for profanity
            if ($this->profanityFilter->hasProfanity($blog->getTitle()) || 
                $this->profanityFilter->hasProfanity($blog->getContent())) {
                $this->addFlash('error_blog', 'Your post contains inappropriate content. Please revise.');
                return $this->render('blog/new.html.twig', [
                    'blog' => $blog,
                    'form' => $form,
                ]);
            }

            // Filter content just in case
            $blog->setTitle($this->profanityFilter->filter($blog->getTitle()));
            $blog->setContent($this->profanityFilter->filter($blog->getContent()));

            $imageFile = $form->get('imageFile')->getData();

            if ($imageFile) {
                $originalFilename = pathinfo($imageFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename.'-'.uniqid().'.'.$imageFile->guessExtension();

                try {
                    $imageFile->move(
                        $this->getParameter('blog_images_directory'),
                        $newFilename
                    );
                    $blog->setImageFilename($newFilename);
                } catch (\Exception $e) {
                    $this->addFlash('error_blog', 'Error uploading image');
                }
            }

            $entityManager->persist($blog);
            $entityManager->flush();

            return $this->redirectToRoute('app_blog_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('blog/new.html.twig', [
            'blog' => $blog,
            'form' => $form,
        ]);
    }


    #[Route('/{id}', name: 'app_blog_show', methods: ['GET'])]
    public function show(Blog $blog): Response
    {
        // Translate the title if not already translated
        if (!$blog->getTranslatedTitle()) {
            $translatedTitle = $this->translationService->translate($blog->getTitle(), 'fr');
            if ($translatedTitle) {
                $blog->setTranslatedTitle($translatedTitle);
                $this->entityManager->flush();
            }
        }

        $comment = new Comment();
        $comment->setBlog($blog);
        $form = $this->createForm(CommentType::class, $comment, [
            'action' => $this->generateUrl('app_blog_add_comment', ['id' => $blog->getId()])
        ]);

        return $this->render('blog/show.html.twig', [
            'blog' => $blog,
            'comment_form' => $form->createView(),
            'is_translated' => true
        ]);
    }

    #[Route('/{id}/translate/{lang}', name: 'app_blog_translate', methods: ['POST'])]
    public function translate(Blog $blog, string $lang): Response
    {
        try {
            $translatedTitle = $this->translationService->translate($blog->getTitle(), $lang);
            $translatedContent = $this->translationService->translate($blog->getContent(), $lang);
            
            if ($translatedTitle && $translatedContent) {
                $blog->setTranslatedTitle($translatedTitle);
                $blog->setTranslatedContent($translatedContent);
                $blog->setTranslationLanguage($lang); // Store the language
                $this->entityManager->flush();
                $this->addFlash('success_blog', 'Blog has been translated successfully.');
                
                // Redirect to the translated view page
                return $this->render('blog/showTranslated.html.twig', [
                    'blog' => $blog,
                    'is_translated' => true
                ]);
            } else {
                throw new \Exception('Translation service returned no result');
            }
        } catch (\Exception $e) {
            $this->addFlash('error_blog', 'Translation failed: ' . $e->getMessage());
            return $this->redirectToRoute('app_blog_show', ['id' => $blog->getId()]);
        }
    }
    #[Route('/{id}/comment', name: 'app_blog_add_comment_to_blog', methods: ['POST'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function addCommentToBlog(Request $request, Blog $blog, EntityManagerInterface $entityManager): Response
    {
        $comment = new Comment();
        $comment->setBlog($blog);
        $comment->setUser($this->getUser());
        $comment->setCreatedAt(new \DateTimeImmutable());
        
        $commentForm = $this->createForm(CommentType::class, $comment);
        $commentForm->handleRequest($request);

        if ($commentForm->isSubmitted() && $commentForm->isValid()) {
            // Ensure user is set again after form submission
            $comment->setUser($this->getUser());
            $entityManager->persist($comment);
            $entityManager->flush();

            $this->addFlash('success_blog', 'Your comment has been added successfully!');
            return $this->redirectToRoute('app_blog_show', ['id' => $blog->getId()]);
        }

        return $this->render('blog/show.html.twig', [
            'blog' => $blog,
            'commentForm' => $commentForm->createView(),
        ]);
    }

    #[Route('/admin/blog', name: 'app_admin_blog_index', methods: ['GET'])]
    public function backendIndex(BlogRepository $blogRepository): Response
    {
        return $this->render('blog_back/posts.html.twig', [
            'blogs' => $blogRepository->findAll(),
        ]);
    }

    #[Route('/admin/blog/{id}/show', name: 'app_blog_back_show', methods: ['GET'])]
    public function backendShow(Blog $blog): Response
    {
        return $this->render('blog_back/show.html.twig', [
            'blog' => $blog,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_blog_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Blog $blog, EntityManagerInterface $entityManager, SluggerInterface $slugger): Response
    {
        $form = $this->createForm(BlogType::class, $blog);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Check for profanity
            if ($this->profanityFilter->hasProfanity($blog->getTitle()) || 
                $this->profanityFilter->hasProfanity($blog->getContent())) {
                $this->addFlash('error_blog', 'Your post contains inappropriate content. Please revise.');
                return $this->render('blog/edit.html.twig', [
                    'blog' => $blog,
                    'form' => $form,
                ]);
            }

            // Filter content just in case
            $blog->setTitle($this->profanityFilter->filter($blog->getTitle()));
            $blog->setContent($this->profanityFilter->filter($blog->getContent()));

            $imageFile = $form->get('imageFile')->getData();

            if ($imageFile) {
                $originalFilename = pathinfo($imageFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename.'-'.uniqid().'.'.$imageFile->guessExtension();

                try {
                    // Delete old image if it exists
                    if ($blog->getImageFilename()) {
                        $oldImagePath = $this->getParameter('blog_images_directory') . '/' . $blog->getImageFilename();
                        if (file_exists($oldImagePath)) {
                            unlink($oldImagePath);
                        }
                    }
                    
                    $imageFile->move(
                        $this->getParameter('blog_images_directory'),
                        $newFilename
                    );
                    $blog->setImageFilename($newFilename);
                } catch (\Exception $e) {
                    $this->addFlash('error_blog', 'Error uploading image');
                }
            }

            $entityManager->flush();
            $this->addFlash('success_blog', 'Blog post updated successfully!');

            return $this->redirectToRoute('app_blog_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('blog/edit.html.twig', [
            'blog' => $blog,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/delete', name: 'app_blog_delete', methods: ['POST'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function delete(Request $request, Blog $blog, EntityManagerInterface $entityManager): Response
    {
        // Check if the current user is the author of the blog post
        if ($blog->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException('You can only delete your own blog posts.');
        }

        if ($this->isCsrfTokenValid('delete'.$blog->getId(), $request->request->get('_token'))) {
            $entityManager->remove($blog);
            $entityManager->flush();
            
            $this->addFlash('success_blog', 'Your blog post has been deleted successfully.');
        }

        return $this->redirectToRoute('app_blog_index');
    }


}