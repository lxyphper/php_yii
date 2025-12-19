import json
import os

# File paths
extracted_words_path = "/Users/lixiangyang/php-project/my-yii2/console/runtime/tmp/词汇/extracted_words.txt"
word_index_map_path = "/Users/lixiangyang/php-project/my-yii2/console/runtime/tmp/词汇/word_index_map.json"
output_path = "/Users/lixiangyang/php-project/my-yii2/console/runtime/tmp/词汇/word_union.txt"

# Read words from extracted_words.txt
with open(extracted_words_path, 'r', encoding='utf-8') as f:
    extracted_words = set(line.strip() for line in f if line.strip())

# Read words from word_index_map.json
with open(word_index_map_path, 'r', encoding='utf-8') as f:
    word_index_map = json.load(f)
    json_words = set(word_index_map.keys())

# Find union of both sets
union_words = extracted_words.union(json_words)

# Sort the union words alphabetically
sorted_union = sorted(union_words)

# Write to output file
with open(output_path, 'w', encoding='utf-8') as f:
    for word in sorted_union:
        f.write(word + '\n')

print(f"Extracted {len(extracted_words)} words from extracted_words.txt")
print(f"Extracted {len(json_words)} words from word_index_map.json")
print(f"Union contains {len(union_words)} unique words")
print(f"Written to: {output_path}")