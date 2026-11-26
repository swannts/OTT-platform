<?php

namespace Tests\Feature;

use App\Services\Dashboard\AnalyticsChartService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Entertainment\Models\Entertainment;
use Modules\Entertainment\Models\Review;
use Modules\Genres\Models\Genres;
use Tests\TestCase;

class AdminDashboardChartsTest extends TestCase
{
    use RefreshDatabase;

    public function test_parse_date_range_and_comparison_helpers(): void
    {
        $service = new AnalyticsChartService;

        [$singleStart, $singleEnd] = $service->parseDateRange('2026-03-15');
        $this->assertSame('2026-03-15 00:00:00', $singleStart->format('Y-m-d H:i:s'));
        $this->assertSame('2026-03-15 23:59:59', $singleEnd->format('Y-m-d H:i:s'));

        [$rangeStart, $rangeEnd] = $service->parseDateRange('2026-03-01 to 2026-03-10');
        [$curStart, $curEnd, $prevStart, $prevEnd] = $service->resolveComparisonPeriods($rangeStart, $rangeEnd);

        $this->assertSame('2026-03-01 00:00:00', $curStart->format('Y-m-d H:i:s'));
        $this->assertSame('2026-03-10 23:59:59', $curEnd->format('Y-m-d H:i:s'));
        $this->assertSame('2026-02-28 23:59:59', $prevEnd->format('Y-m-d H:i:s'));
        $this->assertTrue($prevStart->isBefore($prevEnd));

        $this->assertSame([0, true], $service->computePercentageChange(0, 0));
        $this->assertSame([100, true], $service->computePercentageChange(50, 0));
        $this->assertSame([50.0, true], $service->computePercentageChange(150, 100));
        $this->assertSame([25.0, false], $service->computePercentageChange(75, 100));

        $trendDates = $service->generateTrendDates(
            Carbon::parse('2026-02-01'),
            Carbon::parse('2026-02-10'),
            $curStart,
            $curEnd
        );
        $this->assertCount(2, $trendDates);
    }

    public function test_genre_and_top_rated_chart_data(): void
    {
        $genreAction = Genres::create([
            'name' => 'Action',
            'slug' => 'action',
            'status' => 1,
        ]);
        $genreDrama = Genres::create([
            'name' => 'Drama',
            'slug' => 'drama',
            'status' => 1,
        ]);

        $movie = Entertainment::create([
            'name' => 'Action Hero',
            'slug' => 'action-hero',
            'type' => 'movie',
            'status' => 1,
        ]);
        $tvshow = Entertainment::create([
            'name' => 'Drama Series',
            'slug' => 'drama-series',
            'type' => 'tvshow',
            'status' => 1,
        ]);

        DB::table('entertainment_gener_mapping')->insert([
            [
                'id' => (string) Str::uuid(),
                'entertainment_id' => $movie->id,
                'genre_id' => $genreAction->id,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => (string) Str::uuid(),
                'entertainment_id' => $tvshow->id,
                'genre_id' => $genreAction->id,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => (string) Str::uuid(),
                'entertainment_id' => $tvshow->id,
                'genre_id' => $genreDrama->id,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        Review::create([
            'entertainment_id' => $movie->id,
            'user_id' => 1,
            'rating' => 5,
            'review' => 'Great movie',
        ]);
        Review::create([
            'entertainment_id' => $tvshow->id,
            'user_id' => 1,
            'rating' => 4,
            'review' => 'Great show',
        ]);

        $service = new AnalyticsChartService;

        $genreChart = $service->getGenreChartData();
        $this->assertSame(['Action', 'Drama'], $genreChart['category']);
        $this->assertSame([2, 1], $genreChart['chartData']);

        $topRatedChart = $service->getTopRatedChartData();
        $this->assertArrayHasKey('chartData', $topRatedChart);
        $this->assertArrayHasKey('category', $topRatedChart);
    }
}
