<?php

namespace Sandstorm\NeosH5P\Domain\Repository;

use Neos\Flow\Persistence\Doctrine\Repository;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Security\Account;
use Sandstorm\NeosH5P\Domain\Model\Content;

/**
 * @Flow\Scope("singleton")
 */
class ContentResultRepository extends Repository
{

    public function findOneByContentAndAccount(Content $content, Account $currentAccount)
    {
        return $this->findOneBy(['content' => $content, 'account' => $currentAccount]);
    }

    public function findAllGroupedByAccount(){
        $query = $this->createQuery();
        $query->getQueryBuilder()->addSelect('COUNT(e)')->groupBy('e.account');
        return $query->execute();
    }
}
