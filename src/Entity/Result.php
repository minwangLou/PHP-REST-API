<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use JMS\Serializer\Annotation as Serializer;

#[ORM\Entity]
#[ORM\Table(name: 'results')]
#[Serializer\XmlRoot(name: 'result')]
class Result
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    #[Serializer\XmlAttribute]
    private ?int $id = null;

    #[ORM\Column(type: 'integer')]
    private int $value;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Serializer\Exclude]
    private User $user;

    public function __construct(int $value = 0)
    {
        $this->value = $value;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getValue(): int
    {
        return $this->value;
    }

    public function setValue(int $value): void
    {
        $this->value = $value;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function setUser(User $user): void
    {
        $this->user = $user;
    }

    #[Serializer\VirtualProperty(name: 'userId')]
    public function getUserId(): int
    {
        return $this->user->getId();
    }
}
