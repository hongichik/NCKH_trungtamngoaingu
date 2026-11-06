<?php

namespace App\Support\QuestionTypes\Catalog;

/** Dạng bài chung, dùng cho mọi ngôn ngữ (dữ liệu mẫu bằng tiếng Anh). */
class General
{
    public static function types(): array
    {
        $g = fn (array $d) => $d + ['standard' => 'general', 'lang' => 'any'];

        return [
            $g(['code' => 'general.mcq_single', 'name' => 'Trắc nghiệm 1 đáp án', 'skill' => 'grammar', 'engine' => 'choice',
                'description' => 'Câu hỏi có 3–5 lựa chọn, chọn 1.',
                'sample' => ['instructions' => 'Choose the correct answer.', 'questions' => [
                    ['id' => '1', 'prompt' => 'She ___ to work by bus every day.', 'options' => ['go', 'goes', 'going', 'gone'], 'answer' => 'B', 'explanation' => 'Chủ ngữ số ít, hiện tại đơn → goes.'],
                    ['id' => '2', 'prompt' => 'I have lived here ___ 2019.', 'options' => ['for', 'since', 'from', 'at'], 'answer' => 'B'],
                ]]]),
            $g(['code' => 'general.mcq_multi', 'name' => 'Trắc nghiệm nhiều đáp án', 'skill' => 'vocab', 'engine' => 'multi_choice',
                'description' => 'Chọn tất cả đáp án đúng; điểm từng phần.',
                'sample' => ['instructions' => 'Choose ALL the words that are fruits.', 'questions' => [
                    ['id' => '1', 'prompt' => 'Which of these are fruits?', 'options' => ['apple', 'carrot', 'mango', 'onion', 'grape'], 'answers' => ['A', 'C', 'E']],
                ]]]),
            $g(['code' => 'general.true_false', 'name' => 'Đúng / Sai', 'skill' => 'reading', 'engine' => 'choice',
                'options' => ['choices' => ['True' => 'Đúng (True)', 'False' => 'Sai (False)']],
                'sample' => ['instructions' => 'Read the text. Are the statements true or false?',
                    'stimulus' => ['passage' => "Tom gets up at six o'clock. He has breakfast with his sister and then walks to school. School starts at half past seven."],
                    'questions' => [
                        ['id' => '1', 'prompt' => 'Tom gets up at seven.', 'answer' => 'False'],
                        ['id' => '2', 'prompt' => 'Tom walks to school.', 'answer' => 'True'],
                    ]]]),
            $g(['code' => 'general.gap_typed', 'name' => 'Điền từ — gõ tự do', 'skill' => 'grammar', 'engine' => 'gap_text',
                'sample' => ['instructions' => 'Complete the sentences with the correct form of the verb in brackets.',
                    'text' => "1. Yesterday I [[1]] (watch) a film.\n2. She has never [[2]] (be) to Japan.\n3. Look! The children [[3]] (play) in the garden.",
                    'answers' => ['1' => ['watched'], '2' => ['been'], '3' => ['are playing', "'re playing"]]]]),
            $g(['code' => 'general.gap_dropdown', 'name' => 'Điền từ — chọn trong danh sách thả xuống', 'skill' => 'grammar', 'engine' => 'gap_select',
                'sample' => ['instructions' => 'Choose the correct word for each gap.',
                    'text' => 'There [[1]] a lot of people at the party, and [[2]] of them were dancing.',
                    'gaps' => ['1' => ['options' => ['was', 'were', 'is'], 'answer' => 'were'], '2' => ['options' => ['much', 'many', 'any'], 'answer' => 'many']]]]),
            $g(['code' => 'general.gap_bank', 'name' => 'Điền từ — kéo thả từ ngân hàng từ', 'skill' => 'vocab', 'engine' => 'gap_bank',
                'sample' => ['instructions' => 'Drag the words into the gaps. There is one extra word.',
                    'text' => 'I usually [[1]] my teeth after breakfast, then I [[2]] my bag and [[3]] the bus to school.',
                    'bank' => ['brush', 'pack', 'catch', 'cook'], 'answers' => ['1' => 'brush', '2' => 'pack', '3' => 'catch']]]),
            $g(['code' => 'general.match_pairs', 'name' => 'Nối cặp (từ ↔ nghĩa, câu hỏi ↔ trả lời)', 'skill' => 'vocab', 'engine' => 'matching',
                'sample' => ['instructions' => 'Match each word with its meaning.', 'options_title' => 'Nghĩa',
                    'items' => [['id' => '1', 'text' => 'library'], ['id' => '2', 'text' => 'kitchen'], ['id' => '3', 'text' => 'airport']],
                    'options' => ['nhà bếp', 'sân bay', 'thư viện', 'bệnh viện'], 'answers' => ['1' => 'C', '2' => 'A', '3' => 'B']]]),
            $g(['code' => 'general.match_picture', 'name' => 'Nối hình ↔ từ', 'skill' => 'vocab', 'engine' => 'matching',
                'sample' => ['instructions' => 'Match the pictures with the words.', 'options_title' => 'Hình',
                    'items' => [['id' => '1', 'text' => 'umbrella'], ['id' => '2', 'text' => 'bicycle'], ['id' => '3', 'text' => 'clock']],
                    'options' => ['🚲', '⏰', '☂️'], 'answers' => ['1' => 'C', '2' => 'A', '3' => 'B']]]),
            $g(['code' => 'general.categorize', 'name' => 'Phân loại vào nhóm', 'skill' => 'vocab', 'engine' => 'categorize',
                'sample' => ['instructions' => 'Put the words into the correct group.',
                    'groups' => ['Countable' => ['chair', 'apple', 'idea'], 'Uncountable' => ['water', 'advice', 'furniture']]]]),
            $g(['code' => 'general.order_sentences', 'name' => 'Sắp xếp câu / đoạn', 'skill' => 'reading', 'engine' => 'ordering',
                'sample' => ['instructions' => 'Put the sentences in order to make a story.', 'items' => [
                    'Last Sunday, Anna decided to bake a cake.', 'First, she mixed flour, sugar and eggs.',
                    'Then she put the cake in the oven for forty minutes.', 'Finally, she shared it with her family.',
                ]]]),
            $g(['code' => 'general.word_order', 'name' => 'Sắp xếp từ thành câu', 'skill' => 'grammar', 'engine' => 'word_order',
                'sample' => ['instructions' => 'Put the words in the correct order.', 'questions' => [
                    ['id' => '1', 'tokens' => ['She', 'often', 'goes', 'swimming', 'on', 'Sundays.'], 'accept' => ['On Sundays she often goes swimming.']],
                    ['id' => '2', 'tokens' => ['Where', 'did', 'you', 'buy', 'that', 'bag?']],
                ]]]),
            $g(['code' => 'general.error_correction', 'name' => 'Tìm và sửa lỗi sai', 'skill' => 'grammar', 'engine' => 'error_correction',
                'sample' => ['instructions' => 'Each sentence has ONE mistake. Click it and write the correction.', 'sentences' => [
                    ['id' => '1', 'text' => 'My brother *go* to the gym every morning.', 'correction' => ['goes']],
                    ['id' => '2', 'text' => 'We have known each other *since* five years.', 'correction' => ['for']],
                ]]]),
            $g(['code' => 'general.highlight', 'name' => 'Gạch chân từ trong đoạn', 'skill' => 'grammar', 'engine' => 'highlight',
                'sample' => ['prompt' => 'Underline ALL the verbs in the past simple.',
                    'text' => 'Yesterday we *visited* our grandparents. They live in a small village. We *ate* lunch together and *played* cards.']]),
            $g(['code' => 'general.rewrite', 'name' => 'Viết lại câu (giữ nghĩa, bắt đầu bằng từ cho sẵn)', 'skill' => 'grammar', 'engine' => 'short_text',
                'sample' => ['instructions' => 'Rewrite the sentences so that the meaning stays the same.', 'questions' => [
                    ['id' => '1', 'prompt' => 'It is not necessary for you to come early.', 'start' => "You don't", 'answers' => ["You don't have to come early.", "You don't need to come early."]],
                    ['id' => '2', 'prompt' => 'They built this bridge in 1990.', 'start' => 'This bridge', 'answers' => ['This bridge was built in 1990.']],
                ]]]),
            $g(['code' => 'general.translate_to_target', 'name' => 'Dịch câu Việt → ngoại ngữ', 'skill' => 'writing', 'engine' => 'short_text',
                'description' => 'Máy chấm theo danh sách câu dịch chấp nhận; cần nhiều cách dịch thì liệt kê đủ.',
                'sample' => ['instructions' => 'Translate into English.', 'questions' => [
                    ['id' => '1', 'prompt' => 'Tôi đã sống ở Hà Nội được ba năm.', 'answers' => ['I have lived in Hanoi for three years.', "I've lived in Hanoi for three years.", 'I have been living in Hanoi for three years.']],
                ]]]),
            $g(['code' => 'general.translate_to_vi', 'name' => 'Dịch câu ngoại ngữ → Việt', 'skill' => 'reading', 'engine' => 'short_text',
                'sample' => ['instructions' => 'Dịch sang tiếng Việt.', 'questions' => [
                    ['id' => '1', 'prompt' => 'Could you open the window, please?', 'answers' => ['Bạn có thể mở cửa sổ được không?', 'Bạn mở cửa sổ giúp mình được không?', 'Làm ơn mở cửa sổ giúp tôi.']],
                ]]]),
            $g(['code' => 'general.dictation', 'name' => 'Nghe chép chính tả', 'skill' => 'listening', 'engine' => 'dictation',
                'sample' => ['instructions' => 'Listen and write the sentence you hear.', 'questions' => [
                    ['id' => '1', 'text' => 'The museum opens at nine in the morning.'],
                    ['id' => '2', 'text' => 'Please turn off your phone during the lesson.'],
                ]]]),
            $g(['code' => 'general.listen_gap', 'name' => 'Nghe điền từ', 'skill' => 'listening', 'engine' => 'gap_text',
                'sample' => ['instructions' => 'Listen and complete the notes.',
                    'stimulus' => ['transcript' => 'Good morning everyone. The school trip is on Friday. We will meet at the main gate at eight fifteen. Please bring a packed lunch and a raincoat.'],
                    'text' => "Day of trip: [[1]]\nMeeting place: the main [[2]]\nTime: [[3]]\nBring: a packed lunch and a [[4]]",
                    'answers' => ['1' => ['Friday'], '2' => ['gate'], '3' => ['8:15', '8.15', 'eight fifteen'], '4' => ['raincoat']]]]),
            $g(['code' => 'general.read_aloud', 'name' => 'Đọc to (chấm phát âm)', 'skill' => 'speaking', 'engine' => 'speaking', 'grading' => 'ai',
                'options' => ['criteria' => ['Phát âm', 'Trọng âm, ngữ điệu', 'Trôi chảy'], 'speak_seconds' => 30],
                'sample' => ['instructions' => 'Read the text aloud.', 'read_text' => 'Every morning I take the bus to school. It usually takes about twenty minutes.']]),
            $g(['code' => 'general.speak_answer', 'name' => 'Trả lời câu hỏi bằng giọng nói', 'skill' => 'speaking', 'engine' => 'speaking', 'grading' => 'ai',
                'sample' => ['prompt' => 'What do you usually do at the weekend? Talk for about 30 seconds.', 'prep_seconds' => 15, 'speak_seconds' => 45,
                    'sample_answer' => 'At the weekend I usually sleep a bit later, then I meet my friends…']]),
            $g(['code' => 'general.describe_picture', 'name' => 'Mô tả tranh', 'skill' => 'speaking', 'engine' => 'speaking', 'grading' => 'ai',
                'sample' => ['prompt' => 'Describe the picture: who is there, what are they doing, where are they?', 'image' => '/images/qtypes/scene.svg', 'prep_seconds' => 20, 'speak_seconds' => 60]]),
            $g(['code' => 'general.paragraph', 'name' => 'Viết đoạn văn có tiêu chí chấm', 'skill' => 'writing', 'engine' => 'writing', 'grading' => 'ai',
                'sample' => ['prompt' => 'Write a paragraph (80–120 words) about your best friend.', 'min_words' => 80, 'max_words' => 120,
                    'ideas' => ['Tên, tuổi, quen nhau thế nào', 'Ngoại hình, tính cách', 'Hai bạn thường làm gì cùng nhau'],
                    'sample_answer' => 'My best friend is Lan. We met in grade six and have been close friends ever since…']]),
            $g(['code' => 'general.essay', 'name' => 'Viết bài luận', 'skill' => 'writing', 'engine' => 'writing', 'grading' => 'ai',
                'sample' => ['prompt' => 'Some people think students should wear school uniforms. Do you agree or disagree? Write 200–250 words.', 'min_words' => 200, 'max_words' => 250, 'time_minutes' => 40]]),
            $g(['code' => 'general.image_label', 'name' => 'Gắn nhãn lên hình', 'skill' => 'vocab', 'engine' => 'matching',
                'description' => 'Hình có các điểm đánh số; học viên chọn nhãn cho từng điểm.',
                'sample' => ['instructions' => 'Label the room. Choose the correct word for each number.',
                    'stimulus' => ['image' => '/images/qtypes/scene.svg', 'markers' => [['key' => '1', 'x' => 22, 'y' => 30], ['key' => '2', 'x' => 52, 'y' => 62], ['key' => '3', 'x' => 82, 'y' => 40]]],
                    'items' => [['id' => '1', 'text' => 'Point 1'], ['id' => '2', 'text' => 'Point 2'], ['id' => '3', 'text' => 'Point 3']],
                    'options' => ['desk', 'window', 'plant', 'door'], 'answers' => ['1' => 'B', '2' => 'A', '3' => 'C']]]),
            $g(['code' => 'general.hotspot', 'name' => 'Chọn điểm trên ảnh / bản đồ', 'skill' => 'listening', 'engine' => 'hotspot',
                'sample' => ['instructions' => 'Click on the map to show where each place is.', 'image' => '/images/qtypes/map.svg', 'questions' => [
                    ['id' => '1', 'prompt' => 'The library', 'x' => 20, 'y' => 25, 'r' => 9],
                    ['id' => '2', 'prompt' => 'The café', 'x' => 75, 'y' => 70, 'r' => 9],
                ]]]),
        ];
    }
}
