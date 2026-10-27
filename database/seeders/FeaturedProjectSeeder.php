<?php

namespace Database\Seeders;

use App\Models\FeaturedProject;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class FeaturedProjectSeeder extends Seeder
{
    /**
     * Seed representative project records.
     */
    public function run(): void
    {
        $projects = [
            [
                'name' => 'Tổ Hợp Giáo Dục FPT Tại Hà Tĩnh.',
                'description' => [
                    'Trường Tiểu học, THCS và THPT FPT Hà Tĩnh (FPT School Hà Tĩnh) là cơ sở đầu tiên của Hệ thống Phổ thông FPT (FPT Schools) tại tỉnh, đồng thời là cơ sở thứ 8 của hệ thống tại miền Trung.',
                    'Tổ hợp được xây dựng trên khu đất rộng 56.855,2 m2, theo mô hình khuôn viên giáo dục mở, kết hợp giảng đường, không gian sinh hoạt chung, khu thể thao và cảnh quan cây xanh. Khu trường phổ thông liên cấp gồm hai tòa giảng đường Beta và Gamma, mỗi tòa cao 5 tầng, tổng diện tích sàn khoảng 12.560 m2. Các công trình được bố trí quanh sân trung tâm, kết nối với sân vườn, đường dạo và không gian học tập ngoài trời.',
                    'Dự án còn có nhà đa năng ba tầng phục vụ hoạt động thể thao, nghệ thuật; nhà học võ Vovinam; khu thể thao ngoài trời và các hạng mục phụ trợ. Hệ thống không gian chức năng được thiết kế để phục vụ học tập, rèn luyện thể chất và tổ chức hoạt động tập thể.',
                    'Thiết kế công trình lấy cảm hứng từ hình ảnh cây tre, gắn với sức sống và tinh thần bền bỉ của con người Hà Tĩnh. Hình tượng này được thể hiện qua hệ lam trên mặt đứng, góp phần tạo đặc điểm kiến trúc và che nắng cho công trình, phù hợp với điều kiện khí hậu địa phương.',
                    'Sau lễ cất nóc, các hạng mục hoàn thiện công trình, nhà học võ, khu thể thao và cảnh quan tiếp tục được triển khai. Dự án dự kiến đưa vào hoạt động từ năm học 2027-2028.',
                    'Bên cạnh chương trình Giáo dục phổ thông 2018 theo quy định của Bộ Giáo dục và Đào tạo, FPT School Hà Tĩnh dự kiến triển khai chương trình giáo dục theo định hướng thế mạnh của FPT Schools, chú trọng phát triển tiếng Anh, năng lực toàn cầu, công nghệ và phát triển cá nhân qua hoạt động học tập, trải nghiệm thực tiễn.',
                    'Theo đó, học sinh được tiếp cận các lĩnh vực công nghệ như STEM, trí tuệ nhân tạo (AI), Robotics, đồng thời phát triển tư duy sáng tạo, kỹ năng tự lập, khả năng giải quyết vấn đề và tinh thần trách nhiệm với cộng đồng.',
                    'Với sự hiện diện của FPT Schools tại Hà Tĩnh, học sinh, phụ huynh địa phương có thêm lựa chọn giáo dục, đồng thời góp phần mở rộng cơ hội tiếp cận các phương pháp giáo dục hiện đại, kết hợp kiến thức, công nghệ và kỹ năng thực tiễn từ bậc phổ thông.',
                    'Với tiến độ xây dựng hiện tại, FPT School Hà Tĩnh dự kiến đi vào hoạt động giảng dạy và học tập từ năm học 2027-2028, đóng góp vào tiến trình phát triển mạng lưới cơ sở giáo dục và đào tạo nguồn nhân lực tại khu vực Bắc Trung Bộ.',
                ],
                'location' => 'đường Nguyễn Du, phường Thành Sen, thành phố Hà Tĩnh.',
                'category' => 'K-FLEX',
                'subcategory' => 'Cao su lưu hóa NBR',
                'type' => '2',
                'images' => ['FPTS.jpg', 'FPTS1.jpg'],
            ],
        ];

        foreach ($projects as $projectData) {
            $imageFiles = $projectData['images'];
            unset($projectData['images']);
            $projectData['description'] = implode("\n", $projectData['description']);

            $project = FeaturedProject::query()->updateOrCreate(
                ['name' => $projectData['name']],
                $projectData,
            );
            $storedImagePaths = [];

            foreach ($imageFiles as $imageFile) {
                $imageSource = base_path("Modules/KbAdmin/resources/assets/images/projects/{$imageFile}");
                $imageContents = file_get_contents($imageSource);

                if ($imageContents === false) {
                    throw new RuntimeException("Unable to read featured project image at [{$imageSource}].");
                }

                $imagePath = 'featured-projects/'.Str::slug($project->name).'/'.$imageFile;
                if (! Storage::disk('public')->put($imagePath, $imageContents)) {
                    throw new RuntimeException("Unable to store featured project image at [{$imagePath}].");
                }

                $storedImagePaths[] = $imagePath;
            }

            $project->syncImagePaths($storedImagePaths);
        }
    }
}
