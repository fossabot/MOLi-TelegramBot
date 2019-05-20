<?php

namespace MOLiBot\Console\Commands;

use Illuminate\Console\Command;

use Telegram;
use MOLiBot\Services\NcdrRssService;

class NCDR_RSS extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ncdr:check {--init}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check New RSS Feed From NCDR';

    /**
     * @var ncdrRssService
     */
    private $ncdrRssService;

    /** @var \Illuminate\Support\Collection NCDR_to_BOTChannel_list */
    private $NCDR_to_BOTChannel_list;

    /** @var \Illuminate\Support\Collection NCDR_should_mute */
    private $NCDR_should_mute;
    
    /**
     * Create a new command instance.
     *
     * @param NcdrRssService $ncdrRssService
     * 
     * @return void
     */
    public function __construct(NcdrRssService $ncdrRssService)
    {
        parent::__construct();
        
        $this->ncdrRssService = $ncdrRssService;

        $this->NCDR_to_BOTChannel_list = collect(['地震', '土石流', '河川高水位', '降雨', '停班停課', '道路封閉', '雷雨', '颱風']); // 哪些類別的 NCDR 訊息要推到 MOLi 廣播頻道

        $this->NCDR_should_mute = collect(['土石流']); // 哪些類別的 NCDR 訊息要靜音
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $json = $this->ncdrRssService->getNcdrRss();

        $items = $json['entry'];

        foreach ($items as $item) {
            $category = $item['category']['@attributes']['term'];

            if ( !$this->ncdrRssService->checkRssPublished($item['id']) ) {
                if ($this->option('init')) {
                    $chat_id = env('TEST_CHANNEL');
                } else {
                    $chat_id = env('WEATHER_CHANNEL');
                }

                if ($this->NCDR_to_BOTChannel_list->contains($category)) {
                    Telegram::sendMessage([
                        'chat_id' => $chat_id,
                        'text' => trim($item['summary']) . PHP_EOL . '#' . $category
                    ]);
                }

                $this->ncdrRssService->storePublishedRss($item['id'], $category);

                sleep(5);
            }
        }

        $this->info('Mission Complete!');
    }
}
