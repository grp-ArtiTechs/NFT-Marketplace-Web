<?php

namespace App\Controller;

use App\Entity\Blog;
use App\Entity\Comment;
use App\Form\BlogType;
use App\Form\CommentType;
use App\Repository\BlogRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/blog')]
class BlogController extends AbstractController
{
    #[Route(name: 'app_blog_index', methods: ['GET', 'POST'])]
    public function index(Request $request, BlogRepository $blogRepository, EntityManagerInterface $entityManager): Response
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
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function addComment(Request $request, Blog $blog, EntityManagerInterface $entityManager): Response
    {
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

            $this->addFlash('success', 'Your comment has been added successfully!');
        }

        return $this->redirectToRoute('app_blog_index');
    }

    #[Route('/new', name: 'app_blog_new', methods: ['GET', 'POST'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $blog = new Blog();
        $blog->setDate(new \DateTime());
        $blog->setUser($this->getUser()); // Set the current user as the blog author
        
        $form = $this->createForm(BlogType::class, $blog);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($blog);
            $entityManager->flush();

            $this->addFlash('success', 'Blog post created successfully!');
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
        $comment = new Comment();
        $comment->setBlog($blog);
        $commentForm = $this->createForm(CommentType::class, $comment, [
            'action' => $this->generateUrl('app_blog_add_comment_to_blog', ['id' => $blog->getId()])
        ]);

        return $this->render('blog/show.html.twig', [
            'blog' => $blog,
            'commentForm' => $commentForm->createView(),
        ]);
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

            $this->addFlash('success', 'Your comment has been added successfully!');
            return $this->redirectToRoute('app_blog_show', ['id' => $blog->getId()]);
        }

        return $this->render('blog/show.html.twig', [
            'blog' => $blog,
            'commentForm' => $commentForm->createView(),
        ]);
    }

    #[Route('/{id}/edit', name: 'app_blog_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Blog $blog, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(BlogType::class, $blog);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

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
            
            $this->addFlash('success', 'Your blog post has been deleted successfully.');
        }

        return $this->redirectToRoute('app_blog_index');
    }
}