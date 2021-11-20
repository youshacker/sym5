<?php

namespace App\Controller;

use App\Entity\Article;
use App\Form\ArticleType;
use App\Repository\ArticleRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Component\Filesystem\Exception\IOExceptionInterface;
use Symfony\Component\Filesystem\Filesystem;


#[Route('/article')]
class ArticleController extends AbstractController
{
    #[Route('/', name: 'article_index', methods: ['GET'])]
    public function index(ArticleRepository $articleRepository): Response
    {
        return $this->render('article/index.html.twig', [
            'articles' => $articleRepository->findAll(),
        ]);
    }

    #[Route('/new', name: 'article_new', methods: ['GET','POST'])]
    public function new(Request $request, SluggerInterface $slugger): Response
    {
        $article = new Article();

        $form = $this->createForm(ArticleType::class, $article);
        $form->handleRequest($request);


        if ($form->isSubmitted() && $form->isValid()) {
            $pdfFile = $form->get('pdf')->getData();
            if ($pdfFile) {
                $originalFilename = pathinfo($pdfFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename. '-'.uniqid().'.'.$pdfFile->guessExtension();
                try {
                    $pdfFile->move($this->getParameter('pdf_directory'), $newFilename);
                }
                catch (FileException $e) {
                    echo 'Erreur : ' . $e->getMessage() . '. Code : ' . $e->getCode();
                }

                $article->setPdf($newFilename);

            }

            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->persist($article);
            $entityManager->flush();
            $this->addFlash('notice', 'L\'article a bien été enregistré !');


            return $this->redirectToRoute('article_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->renderForm('article/new.html.twig', [
            'article' => $article,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'article_show', methods: ['GET'])]
    public function show(Article $article): Response
    {
        return $this->render('article/show.html.twig', [
            'article' => $article,
        ]);
    }

    private function removeOldFile($oldFile) : bool
    {
        $filesystem = new filesystem();
       if ($filesystem->remove($this->getParameter('pdf_directory').'/'.$oldFile))
       {
           $this->addFlash('notice', 'L\'ancien PDF en place.');
       } else
        {
           $this->addFlash('notice', 'L\'ancien PDF supprimé');
       }

       return true;
    }

    #[Route('/{id}/edit', name: 'article_edit', methods: ['GET','POST'])]
    public function edit(Request $request, Article $article, SluggerInterface $slugger): Response
    {
        $form = $this->createForm(ArticleType::class, $article);
        $form->handleRequest($request);
        $oldFileName = $article->getPdf();
        $article->setUpdateAt(new \DateTimeImmutable());

        if ($form->isSubmitted() && $form->isValid()) {
            $pdfFile = $form->get('pdf')->getData();
            if ($pdfFile) {
                $originalFilename = pathinfo($pdfFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename. '-'.uniqid().'.'.$pdfFile->guessExtension();

                try {
                    $pdfFile->move($this->getParameter('pdf_directory'), $newFilename);
                }
                catch (FileException $e) {
                    echo 'Erreur : ' . $e->getMessage() . '. Code : ' . $e->getCode();
                }

               // $oldFile = $oldFileName;
                $this->removeOldFile($oldFileName);
                $article->setPdf($newFilename);

            }
            $this->getDoctrine()->getManager()->flush();

            $this->addFlash('notice', 'L\'article a bien été mis à jour !');

            return $this->redirectToRoute('article_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->renderForm('article/edit.html.twig', [
            'article' => $article,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'article_delete', methods: ['POST'])]
    public function delete(Request $request, Article $article): Response
    {
        if ($this->isCsrfTokenValid('delete'.$article->getId(), $request->request->get('_token'))) {
            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->remove($article);
            $entityManager->flush();
        }

        return $this->redirectToRoute('article_index', [], Response::HTTP_SEE_OTHER);
    }
}
