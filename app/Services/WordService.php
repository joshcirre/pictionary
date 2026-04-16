<?php

declare(strict_types=1);

namespace App\Services;

final class WordService
{
    /** @var string[] */
    private static array $words = [
        // Animals
        'cat', 'dog', 'elephant', 'giraffe', 'penguin', 'dolphin', 'butterfly', 'octopus',
        'kangaroo', 'flamingo', 'turtle', 'parrot', 'crocodile', 'hedgehog', 'porcupine',
        'peacock', 'jellyfish', 'lobster', 'seahorse', 'chameleon', 'armadillo', 'platypus',
        'zebra', 'rhinoceros', 'chimpanzee', 'gorilla', 'panda', 'koala', 'sloth',
        'raccoon', 'skunk', 'beaver', 'otter', 'walrus', 'narwhal', 'toucan', 'pelican',
        // Objects
        'umbrella', 'telescope', 'lighthouse', 'mailbox', 'escalator', 'hammock', 'trampoline',
        'wheelbarrow', 'accordion', 'binoculars', 'chandelier', 'compass', 'doorbell', 'easel',
        'funnel', 'globe', 'hourglass', 'ironing board', 'jack-in-the-box', 'kite', 'lantern',
        'magnifying glass', 'microscope', 'necklace', 'parachute', 'quilt', 'rocking chair',
        'seesaw', 'thermometer', 'typewriter', 'vase', 'watering can', 'xylophone', 'yo-yo',
        'zipper', 'blender', 'calculator', 'calendar', 'candle', 'clock', 'crown',
        'diploma', 'envelope', 'fire hydrant', 'flag', 'flashlight', 'glasses', 'glove',
        'hammer', 'hat', 'key', 'ladder', 'lamp', 'lock', 'map', 'mirror', 'mug',
        'newspaper', 'notebook', 'paintbrush', 'pillow', 'pin', 'pizza', 'plug',
        'purse', 'puzzle', 'radio', 'ruler', 'scissors', 'skateboard', 'sled',
        'snowglobe', 'sock', 'sofa', 'sponge', 'stapler', 'stool', 'stopwatch',
        'suitcase', 'sunglasses', 'surfboard', 'swing', 'toaster', 'toilet', 'toolbox',
        'trophy', 'umbrella', 'wagon', 'wallet', 'wand', 'watch', 'whistle',
        // Food
        'banana', 'broccoli', 'burger', 'cake', 'carrot', 'cherry', 'cookie', 'cupcake',
        'donut', 'egg', 'french fries', 'grapes', 'hotdog', 'ice cream', 'lemon',
        'mushroom', 'pancake', 'pineapple', 'popcorn', 'pretzel', 'sandwich', 'spaghetti',
        'strawberry', 'sushi', 'taco', 'watermelon',
        // Places / structures
        'barn', 'bridge', 'castle', 'cave', 'church', 'cliff', 'factory', 'fountain',
        'igloo', 'island', 'library', 'pyramid', 'skyscraper', 'tent', 'treehouse',
        'volcano', 'windmill',
        // Vehicles
        'ambulance', 'bicycle', 'bulldozer', 'canoe', 'helicopter', 'hot air balloon',
        'motorcycle', 'rocket', 'sailboat', 'school bus', 'submarine', 'tractor',
        'train', 'tricycle', 'wheelchair',
    ];

    public function random(): string
    {
        return self::$words[array_rand(self::$words)];
    }
}
