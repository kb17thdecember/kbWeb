<?php

namespace Database\Seeders;

use App\Models\FeaturedProject;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;
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
                'name' => 'Tổ Hợp Giáo Dục FPT Tại Hà Tĩnh',
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
                'products' => [
                    'K-Flex M-Flex',
                ],
                'type' => '2',
                'images' => ['FPTS.jpg', 'FPTS1.jpg', 'FPTS2.jpg'],
            ],
            [
                'name' => 'Crystal Holidays Harbours Vân Đồn',
                'description' => [
                    'Crystal Holidays Harbour Vân Đồn là tổ hợp du lịch, giải trí và nghỉ dưỡng cao cấp tiêu chuẩn 5–6 sao, bao gồm khách sạn 5 sao, căn hộ khách sạn và tòa nhà trung tâm hội nghị quốc tế.',
                    'Dự án do Công ty Cổ phần Đầu tư Everland làm chủ đầu tư, được quy hoạch trên tổng diện tích 234.181,41 m² với tổng vốn đầu tư khoảng 3.511 tỷ đồng. Đây là một trong những dự án du lịch nghỉ dưỡng trọng điểm tại Vân Đồn, góp phần thúc đẩy phát triển du lịch địa phương, thu hút du khách, thương gia và các nhà đầu tư trong nước cũng như quốc tế.',
                    'Lấy cảm hứng từ ý tưởng tái hiện huyền thoại thương cảng Vân Đồn trong thế kỷ XXI, Crystal Holidays Harbour được kiến tạo với định hướng trở thành biểu tượng mới của sự phồn hoa và đẳng cấp, nơi giao thoa giữa văn hóa và kiến trúc Đông – Tây.',
                    'Sau khi hoàn thành và đưa vào khai thác từ năm 2025 theo kế hoạch ban đầu, dự án hướng tới trở thành điểm đến nghỉ dưỡng khác biệt, đồng thời tạo điểm nhấn kiến trúc tại trung tâm đô thị Vân Đồn, Quảng Ninh.',
                    'Crystal Holidays Harbour tọa lạc trên tuyến đường DT344 – trục giao thông quan trọng của huyện Vân Đồn, thuận tiện kết nối với các công trình tiện ích, khu đô thị, trung tâm hành chính, cảng biển và những địa điểm du lịch nổi tiếng trong khu vực. Dự án cách chợ Hạ Long Vân Đồn, chợ Cái Rồng, UBND huyện Vân Đồn, siêu thị Điện máy XANH Vân Đồn, cảng Cái Rồng và cảng tàu du lịch Cái Rồng khoảng 5–10 phút di chuyển.',
                    'Trong phạm vi khoảng 10–15 phút di chuyển, từ dự án có thể tiếp cận các tiện ích như bưu điện Vân Đồn, khu đô thị Ocean Park Vân Đồn và Trường THPT Hải Đảo. Bên cạnh đó, dự án còn nằm gần các điểm đến du lịch nổi bật như Sonasea Vân Đồn Harbor City, Vịnh Bái Tử Long, Khu du lịch sinh thái biển ATI – Bái Tử Long, chùa Cái Bầu (Thiền viện Trúc Lâm Giác Tâm) và chùa Quan Lạn.',
                    'Từ Crystal Holidays Harbour, việc di chuyển đến các địa điểm du lịch khác của Quảng Ninh như huyện đảo Cô Tô, đền Cửa Ông, khu vực sân bay Vân Đồn, Cái Rồng và Bắc Cái Bầu cũng tương đối thuận tiện. Với lợi thế về vị trí, dự án được định hướng trở thành điểm dừng chân nghỉ dưỡng và lưu trú trên hành trình khám phá vùng đất bên Vịnh Bái Tử Long.',
                    'Dự án được đầu tư hệ thống tiện ích nghỉ dưỡng cao cấp, hướng tới đáp ứng đa dạng nhu cầu lưu trú, hội nghị, giải trí và trải nghiệm của du khách. Điểm nhấn là hệ thống khách sạn 5–6 sao và căn hộ khách sạn cao cấp, trong đó có các căn President diện tích lên tới 1.000 m², được giới thiệu với thiết kế dát vàng, hướng tới phân khúc khách hàng VVIP, nguyên thủ quốc gia và giới thượng lưu quốc tế.',
                    'Chúng tôi rất vinh dự được đồng hành cùng chủ đầu tư và các nhà thầu trong việc cung cấp giải pháp bảo ôn K-Flex model K-Protect cho dự án Crystal Holidays Harbour Vân Đồn. Đây là dòng vật liệu bảo ôn cao cấp đến từ thương hiệu K-Flex của Ý, được phát triển nhằm đáp ứng các yêu cầu kỹ thuật về cách nhiệt, bảo vệ hệ thống đường ống và thiết bị cơ điện trong các công trình xây dựng hiện đại.',
                    'Việc lựa chọn giải pháp bảo ôn K-Flex K-Protect góp phần nâng cao hiệu quả vận hành hệ thống cơ điện, hỗ trợ kiểm soát nhiệt độ, hạn chế thất thoát năng lượng và bảo vệ thiết bị trong quá trình sử dụng. Sự hiện diện của sản phẩm tại dự án tiếp tục khẳng định năng lực cung cấp vật tư và giải pháp kỹ thuật của chúng tôi trong lĩnh vực HVAC và cơ điện công trình.'
                ],
                'location' => 'Lô M1, Khu Đô Thị Du Lịch Và Bến Cảng Cao Cấp Ao Tiên, Đăng Khu Vân Đồn, Quảng Ninh.',
                'category' => 'K-FLEX',
                'subcategory' => 'Polyolefin XLPE',
                'products' => [
                    'K-Flex K-Protect',
                ],
                'type' => '1',
                'images' => ['CRYSTAL-HABOUR1.jpg', 'CRYSTAL-HABOUR2.jpg', 'CRYSTAL-HABOUR3.jpg'],
            ],
        ];

        $hasProductsColumn = Schema::hasColumn('featured_projects', 'products');

        foreach ($projects as $projectData) {
            $imageFiles = $projectData['images'];
            unset($projectData['images']);
            if (! $hasProductsColumn) {
                unset($projectData['products']);
            }
            $projectData['description'] = implode("\n", $projectData['description']);

            FeaturedProject::query()
                ->where('name', $projectData['name'].'.')
                ->delete();

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
