<?php

namespace App\Controller;

use App\Entity\Note;
use App\Form\NoteType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class NoteController extends AbstractController
{
    private const LIST_LIMIT = 100;

    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    #[Route('/', name: 'notes', methods: ['GET', 'POST'])]
    public function index(Request $request): Response
    {
        $note = new Note();
        $form = $this->createForm(NoteType::class, $note);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $this->em->persist($note);
            $this->em->flush();

            return $this->redirectToRoute('notes', status: 303);
        }

        $notes = $this->em->getRepository(Note::class)->findBy([], ['updatedAt' => 'DESC', 'id' => 'DESC'], self::LIST_LIMIT);

        return $this->render('notes/index.html.twig', ['form' => $form, 'notes' => $notes],
            new Response(status: $form->isSubmitted() ? 422 : 200));
    }

    #[Route('/notes/{id}/edit', name: 'note_edit', requirements: ['id' => '\d{1,18}'], methods: ['GET', 'POST'])]
    public function edit(Request $request, int $id): Response
    {
        $note = $this->find($id);
        $form = $this->createForm(NoteType::class, $note);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $note->touch();
            $this->em->flush();

            return $this->redirectToRoute('notes', status: 303);
        }

        return $this->render('notes/edit.html.twig', ['form' => $form, 'note' => $note],
            new Response(status: $form->isSubmitted() ? 422 : 200));
    }

    #[Route('/notes/{id}/delete', name: 'note_delete', requirements: ['id' => '\d{1,18}'], methods: ['POST'])]
    public function delete(Request $request, int $id): Response
    {
        if (!$this->isCsrfTokenValid('submit', $request->getPayload()->getString('_token'))) {
            return new Response('Invalid CSRF token', 403);
        }
        $this->em->remove($this->find($id));
        $this->em->flush();

        return $this->redirectToRoute('notes', status: 303);
    }

    private function find(int $id): Note
    {
        return $this->em->find(Note::class, $id) ?? throw $this->createNotFoundException('No such note');
    }
}
