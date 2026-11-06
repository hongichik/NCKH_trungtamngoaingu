<?php

namespace App\Support\QuestionTypes\Catalog;

/** Dạng bài tiếng Trung: HSK 1–6, HSKK và các dạng riêng (thanh điệu, pinyin, bộ thủ, thứ tự nét). */
class Chinese
{
    public static function types(): array
    {
        $h = fn (array $d) => $d + ['standard' => 'hsk', 'lang' => 'zh', 'scoring' => ['scale' => 'hsk']];
        $k = fn (array $d) => $d + ['standard' => 'hskk', 'lang' => 'zh', 'skill' => 'speaking', 'engine' => 'speaking', 'grading' => 'ai',
            'options' => ['criteria' => ['Phát âm & thanh điệu', 'Trôi chảy', 'Từ vựng', 'Ngữ pháp']]];
        $z = fn (array $d) => $d + ['standard' => 'zh', 'lang' => 'zh'];
        $tf = ['choices' => ['√' => '✔ Đúng', '×' => '✘ Sai']];

        return [
            // ------------------------------------------------------------ HSK NGHE
            $h(['code' => 'hsk.listen_tf_picture', 'name' => 'HSK Nghe — Phán đoán đúng / sai theo tranh', 'skill' => 'listening', 'engine' => 'choice', 'options' => $tf,
                'description' => 'HSK 1–2: nghe một từ / câu, xem tranh, chọn đúng hay sai.',
                'sample' => ['instructions' => '听录音，判断对错。', 'stimulus' => ['transcript' => '苹果。 ... 我喜欢喝茶。'], 'questions' => [
                    ['id' => '1', 'prompt' => '🍎', 'answer' => '√'],
                    ['id' => '2', 'prompt' => '☕ (咖啡)', 'answer' => '×'],
                ]]]),
            $h(['code' => 'hsk.listen_choose_picture', 'name' => 'HSK Nghe — Chọn tranh', 'skill' => 'listening', 'engine' => 'choice',
                'sample' => ['instructions' => '听录音，选择对应的图片。', 'stimulus' => ['transcript' => '我坐出租车去机场。'], 'questions' => [
                    ['id' => '1', 'options' => ['🚲', '🚕', '✈️'], 'answer' => 'B'],
                ]]]),
            $h(['code' => 'hsk.listen_dialogue_mcq', 'name' => 'HSK Nghe — Hội thoại, chọn đáp án', 'skill' => 'listening', 'engine' => 'choice',
                'sample' => ['instructions' => '听对话，选择正确答案。', 'stimulus' => ['transcript' => '女：你明天几点去上班？ 男：八点半。问：男的明天几点上班？'], 'questions' => [
                    ['id' => '1', 'options' => ['八点', '八点半', '九点'], 'answer' => 'B'],
                ]]]),

            // ------------------------------------------------------------ HSK ĐỌC
            $h(['code' => 'hsk.match_sentence_picture', 'name' => 'HSK Đọc — Nối câu với tranh', 'skill' => 'reading', 'engine' => 'matching',
                'sample' => ['instructions' => '看图片，选择对应的句子。', 'options_title' => '图片',
                    'items' => [['id' => '1', 'text' => '他在看书。'], ['id' => '2', 'text' => '今天下雨了。'], ['id' => '3', 'text' => '我们去吃米饭吧。']],
                    'options' => ['🍚', '📖', '🌧️'], 'answers' => ['1' => 'B', '2' => 'C', '3' => 'A']]]),
            $h(['code' => 'hsk.fill_word', 'name' => 'HSK Đọc — Chọn từ điền vào chỗ trống', 'skill' => 'reading', 'engine' => 'gap_bank',
                'sample' => ['instructions' => '选词填空。',
                    'text' => "1. 我[[1]]学习汉语。\n2. 这件衣服太[[2]]了，我不买。\n3. 你[[3]]去过北京吗？",
                    'bank' => ['喜欢', '贵', '以前', '高兴'], 'answers' => ['1' => '喜欢', '2' => '贵', '3' => '以前']]]),
            $h(['code' => 'hsk.order_sentences', 'name' => 'HSK Đọc — Sắp xếp câu (ABC)', 'skill' => 'reading', 'engine' => 'ordering', 'scoring' => ['scale' => 'hsk', 'partial' => false],
                'description' => 'HSK 4: sắp xếp 3 câu thành đoạn văn đúng thứ tự (chấm cả câu).',
                'sample' => ['instructions' => '排列顺序。', 'items' => ['我昨天去商店买东西，', '但是忘了带钱包，', '所以什么也没买。']]]),
            $h(['code' => 'hsk.reading_mcq', 'name' => 'HSK Đọc — Đọc đoạn văn trả lời câu hỏi', 'skill' => 'reading', 'engine' => 'choice',
                'sample' => ['instructions' => '阅读短文，选择正确答案。',
                    'stimulus' => ['passage' => '小王每天早上六点起床，先跑步半个小时，然后吃早饭。他说运动让他一天都很有精神。'],
                    'questions' => [['id' => '1', 'prompt' => '小王起床以后先做什么？', 'options' => ['吃早饭', '跑步', '上班'], 'answer' => 'B']]]]),

            // ------------------------------------------------------------ HSK VIẾT
            $h(['code' => 'hsk.word_order', 'name' => 'HSK Viết — Sắp xếp từ thành câu (HSK 3+)', 'skill' => 'writing', 'engine' => 'word_order',
                'sample' => ['instructions' => '完成句子。', 'questions' => [
                    ['id' => '1', 'tokens' => ['我', '把', '作业', '写完', '了'], 'accept' => []],
                    ['id' => '2', 'tokens' => ['他', '比', '我', '高', '一点儿']],
                ]]]),
            $h(['code' => 'hsk.pinyin_to_hanzi_fill', 'name' => 'HSK Viết — Điền chữ Hán theo pinyin', 'skill' => 'writing', 'engine' => 'gap_text',
                'sample' => ['instructions' => '看拼音写汉字。', 'text' => "1. 今天天气很 [[1]] (hǎo)。\n2. 我 [[2]] (ài) 我的家。", 'answers' => ['1' => ['好'], '2' => ['爱']]]]),
            $h(['code' => 'hsk.sentence_with_word', 'name' => 'HSK Viết — Viết câu với từ cho sẵn', 'skill' => 'writing', 'engine' => 'writing', 'grading' => 'ai',
                'options' => ['criteria' => ['Dùng đúng từ', 'Ngữ pháp', 'Chữ Hán']],
                'sample' => ['prompt' => '用所给词语造句：一边……一边……', 'keywords' => ['一边'], 'min_words' => 6, 'sample_answer' => '他一边听音乐一边做作业。']]),
            $h(['code' => 'hsk.paragraph_picture', 'name' => 'HSK Viết — Viết đoạn văn theo tranh / từ khoá (HSK 5–6)', 'skill' => 'writing', 'engine' => 'writing', 'grading' => 'ai',
                'options' => ['criteria' => ['Nội dung', 'Mạch lạc', 'Từ vựng', 'Ngữ pháp & chữ Hán']],
                'sample' => ['prompt' => '请结合下列词语写一篇80字左右的短文：毕业、紧张、面试、准备、终于', 'keywords' => ['毕业', '紧张', '面试', '准备', '终于'], 'min_words' => 80, 'max_words' => 100]]),
            $h(['code' => 'hsk.summary', 'name' => 'HSK 6 — Tóm tắt (缩写)', 'skill' => 'writing', 'engine' => 'writing', 'grading' => 'ai',
                'options' => ['criteria' => ['Đủ ý chính', 'Mạch lạc', 'Từ vựng', 'Ngữ pháp & chữ Hán']],
                'sample' => ['stimulus' => ['passage' => '有一个年轻人去一家公司面试。面试结束后，他看到地上有一张纸，就把它捡起来扔进了垃圾桶。经理看到了，马上决定录用他。经理说：“能力可以培养，但是好习惯更难得。”'],
                    'prompt' => '阅读上文，用自己的话把它缩写成一篇短文（约 60 字），不需要加入自己的观点。', 'min_words' => 50, 'max_words' => 80]]),

            // ------------------------------------------------------------ RIÊNG TIẾNG TRUNG
            $z(['code' => 'zh.tone_choice', 'name' => 'Tiếng Trung — Chọn thanh điệu', 'skill' => 'pronunciation', 'engine' => 'choice',
                'options' => ['choices' => ['1' => 'ˉ Thanh 1', '2' => 'ˊ Thanh 2', '3' => 'ˇ Thanh 3', '4' => 'ˋ Thanh 4']],
                'sample' => ['instructions' => '听一听，选择声调。', 'stimulus' => ['transcript' => '妈。 ... 马。'], 'questions' => [
                    ['id' => '1', 'prompt' => '妈 (ma)', 'answer' => '1'],
                    ['id' => '2', 'prompt' => '马 (ma)', 'answer' => '3'],
                ]]]),
            $z(['code' => 'zh.listen_pinyin', 'name' => 'Tiếng Trung — Nghe chọn pinyin', 'skill' => 'listening', 'engine' => 'choice',
                'sample' => ['instructions' => '听录音，选择正确的拼音。', 'stimulus' => ['transcript' => '谢谢。'], 'questions' => [
                    ['id' => '1', 'options' => ['xièxie', 'xiéxie', 'shèshe'], 'answer' => 'A'],
                ]]]),
            $z(['code' => 'zh.pinyin_to_hanzi', 'name' => 'Tiếng Trung — Pinyin → chữ Hán', 'skill' => 'vocab', 'engine' => 'choice',
                'sample' => ['instructions' => '选择拼音对应的汉字。', 'questions' => [
                    ['id' => '1', 'prompt' => 'péngyou', 'options' => ['朋友', '明天', '老师'], 'answer' => 'A'],
                    ['id' => '2', 'prompt' => 'xuéxiào', 'options' => ['学生', '学校', '医院'], 'answer' => 'B'],
                ]]]),
            $z(['code' => 'zh.radical_match', 'name' => 'Tiếng Trung — Ghép bộ thủ', 'skill' => 'vocab', 'engine' => 'matching', 'options' => ['reuse' => true],
                'sample' => ['instructions' => '找出每个汉字的部首。', 'options_title' => '部首',
                    'items' => [['id' => '1', 'text' => '妈'], ['id' => '2', 'text' => '河'], ['id' => '3', 'text' => '说']],
                    'options' => [['key' => '女', 'text' => '女 (nữ)'], ['key' => '氵', 'text' => '氵(thuỷ)'], ['key' => '讠', 'text' => '讠(ngôn)'], ['key' => '口', 'text' => '口 (khẩu)']],
                    'answers' => ['1' => '女', '2' => '氵', '3' => '讠']]]),
            $z(['code' => 'zh.stroke_order', 'name' => 'Tiếng Trung — Viết chữ theo thứ tự nét (Hanzi Writer)', 'skill' => 'writing', 'engine' => 'hanzi',
                'sample' => ['instructions' => '按笔顺写汉字。', 'characters' => '你好', 'pinyin' => 'nǐ hǎo', 'meaning' => 'xin chào']]),

            // ------------------------------------------------------------ HSKK
            $k(['code' => 'hskk.repeat', 'name' => 'HSKK — Nghe nhắc lại (听后重复)', 'options' => ['criteria' => ['Chính xác', 'Phát âm & thanh điệu'], 'speak_seconds' => 15],
                'sample' => ['instructions' => '听后重复。', 'repeat_text' => '我每天七点起床。']]),
            $k(['code' => 'hskk.read_aloud', 'name' => 'HSKK — Đọc to (朗读)',
                'sample' => ['instructions' => '朗读下面的短文。', 'read_text' => '我家附近有一个小公园。每天晚上，很多人在那里散步、跳舞。我也常常和妈妈一起去。', 'prep_seconds' => 30, 'speak_seconds' => 60]]),
            $k(['code' => 'hskk.answer_questions', 'name' => 'HSKK — Trả lời câu hỏi (回答问题)',
                'sample' => ['questions' => ['你喜欢什么运动？为什么？', '周末你一般做什么？'], 'speak_seconds' => 60]]),
            $k(['code' => 'hskk.describe_picture', 'name' => 'HSKK — Mô tả tranh (看图说话)',
                'sample' => ['prompt' => '请描述这张图片。', 'image' => '/images/qtypes/scene.svg', 'prep_seconds' => 30, 'speak_seconds' => 90]]),
            $k(['code' => 'hskk.retell', 'name' => 'HSKK — Nghe và kể lại chuyện (复述)',
                'sample' => ['instructions' => '听一段话，然后复述。', 'repeat_text' => '小明第一次去北京。他去了长城，还吃了北京烤鸭。他觉得北京很大，人也很多。', 'prep_seconds' => 20, 'speak_seconds' => 90]]),
        ];
    }
}
