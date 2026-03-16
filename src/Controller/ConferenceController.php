<?php

namespace App\Controller;

use App\Entity\Comment;
use App\Entity\Conference;
use App\Form\CommentType;
use App\Repository\CommentRepository;
use App\Repository\ConferenceRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ConferenceController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {}

    //render() de AbstractController contient le $twig->render() et l'environnement Twig

    /*#[Route('/', name: 'homepage')]
    public function index(Environment $twig, ConferenceRepository $conferenceRepository): Response
    {
        return new Response($twig->render('conference/index.html.twig', [
            'conferences' => $conferenceRepository->findAll(),
        ]));
    }*/

    #[Route('/', name: 'homepage')]
    public function index(ConferenceRepository $conferenceRepository): Response
    {
        return $this->render('conference/index.html.twig', [
            'conferences' => $conferenceRepository->findAll(),
        ]);
    }
     

    //L'id dans la route va permettre de sélectionner le paramètre Conference
    #[Route('/conference/{slug}', name: 'conference')]
    public function show(
        Request $request, 
        Conference $conference, 
        CommentRepository $commentRepository,
        #[Autowire('%photo_dir%')] string $photoDir,
    ): Response
    {
        //Form
        $comment = new Comment();
        $form = $this->createForm(CommentType::class, $comment); //Pas d'instanciation directe du formulaire
        
        //Traitement et mise à jour de Commet quand le form est soumis
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $comment->setConference($conference);

            if ($photo = $form['photo']->getData()) {
                $filename = bin2hex(random_bytes(6)).'.'.$photo->guessExtension(); //Crée un nom aléatoire
                $photo->move($photoDir, $filename); //$photoDir est stocké dans services.yaml
                $comment->setPhotoFilename($filename);
            }

            $this->entityManager->persist($comment);
            $this->entityManager->flush();

            return $this->redirectToRoute('conference', ['slug' => $conference->getSlug()]);
        }

        //Paginator
        $offset = max(0, $request->query->getInt('offset', 0));
        $paginator = $commentRepository->getCommentPaginator($conference, $offset);

        //return new Response($twig->render('conference/show.html.twig', [
        return $this->render('conference/show.html.twig', [    
            'conference' => $conference,
            //'comments' => $commentRepository->findBy(['conference' => $conference], ['createdAt' => 'DESC']),
            'comments' => $paginator,
            'previous' => $offset - CommentRepository::COMMENTS_PER_PAGE,
            'next' => min(count($paginator), $offset + CommentRepository::COMMENTS_PER_PAGE),
            'comment_form' => $form,
        //]));
        ]);
    }
 }
