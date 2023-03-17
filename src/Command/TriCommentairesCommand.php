<?php

namespace App\Command;

use OpenAI\Client;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Contracts\Cache\ItemInterface;

#[AsCommand(
    name: 'tri:commentaires',
    description: 'Tri les commentaires clients par degré de satisfaction',
)]
class TriCommentairesCommand extends Command
{
    public function __construct(
        private readonly Client $openai
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $cache = new FilesystemAdapter();

        $commentairesClients = [
            'J\'ai commandé une gaufre et j\'y ai trouvé un ongle et un cheveu. On m\'a dit que je devais attendre 1 heure pour en avoir une autre. On ne m\'a jamais remboursé. Je n\'enverrais même pas mon pire ennemi dans ce restaurant.',
            'Service acceptable. Mais la nourriture n\'était pas bonne (le homard). On avait rdv à 19h, on est arrivé 10 min en retard et notre table avait été donné... alors qu\'il y avait plein de tables libres.',
            'Excellent restaurant avec des produits de qualités et de très bons plats. Les serveurs sont adorables. Nous avons passés un super moment. Nous avons hâte de revenir.',
            'Le cadre est superbe, depuis ce bâtiment très joliment restauré. Prometteur mais décevant. En terrasse, café servi froid ...malgré le prix. Repas avec entrée poir petits appetits, poisson pas ytrès cuit, service minimal. C\'est dommage, ce pourrait être beaucoup mieux.',
            'Aucune fausse note, un lieu magique la nuit , un repas excellent, et une équipe attentive et de très bon conseil. Nous avons passé un très agréable moment ! Nous y retournerons certainement.',
            'Très bon restaurant, service impeccable, cadre agréable, cuisine de qualité. Nous avons passé un très bon moment. Nous recommandons vivement ce restaurant.',
        ];

        $commentairesClientsTri = $cache->get('commentaires_clients_trie', function (ItemInterface $item) use ($commentairesClients) {
            dump('Requête API');
            $item->expiresAfter(3600);

            $result = $this->openai->completions()->create([
                'model' => 'text-davinci-003',
                'prompt' => 'Trier les commentaires clients de ce restaurant du plus sympa au plus méchant: \n\n'.implode('\n', $commentairesClients),
                'max_tokens' => 1000,
            ]);

            return explode('\n', trim($result['choices'][0]['text']));
        });

        foreach ($commentairesClientsTri as $commentaire) {
            $output->writeln($commentaire);
        }

        $item = $cache->getItem('commentaires_clients_trie');
        $metadata = $item->getMetadata();
        dump($metadata);
        $expirationTimeInMinutes = round(($metadata['expiry'] - time()) / 60);
        $output->writeln([
            '============',
            'Cette donnée va expirer dans '.$expirationTimeInMinutes.' minutes',
        ]);

        $io->success('Traitement terminé');

        return Command::SUCCESS;
    }
}
